<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream\Store;

use function Amp\call;
use function Latitude\QueryBuilder\field;
use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\selectQuery;
use function ServiceBus\Storage\Sql\updateQuery;
use Amp\Promise;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamDoesNotExist;
use ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamIntegrityCheckFailed;
use ServiceBus\Storage\Common\BinaryDataDecoder;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use ServiceBus\Storage\Common\QueryExecutor;

/**
 *
 */
final class SqlEventStreamStore implements EventStreamStore
{
    private const STREAMS_TABLE = 'event_store_stream';

    private const STREAM_EVENTS_TABLE = 'event_store_stream_events';

    private DatabaseAdapter $adapter;

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function save(StoredAggregateEventStream $aggregateEventStream): Promise
    {
        return $this->adapter->transactional(
            static function(QueryExecutor $queryExecutor) use ($aggregateEventStream): \Generator
            {
                yield from self::doSaveStream($queryExecutor, $aggregateEventStream);
                yield from self::doSaveEvents($queryExecutor, $aggregateEventStream);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function append(StoredAggregateEventStream $aggregateEventStream): Promise
    {
        return $this->adapter->transactional(
            static function(QueryExecutor $queryExecutor) use ($aggregateEventStream): \Generator
            {
                yield from self::doSaveEvents($queryExecutor, $aggregateEventStream);
            }
        );
    }

    /**
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * {@inheritdoc}
     */
    public function load(
        AggregateId $id,
        int $fromVersion = Aggregate::START_PLAYHEAD_INDEX,
        ?int $toVersion = null
    ): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(AggregateId $id, int $fromVersion, ?int $toVersion) use ($adapter): \Generator
            {
                $aggregateEventStream = null;

                /** @var array<string, string>|null $streamData */
                $streamData = yield from self::doLoadStream($adapter, $id);

                if (null !== $streamData)
                {
                    /** @var array<int, array>|null $streamEventsData */
                    $streamEventsData = yield from self::doLoadStreamEvents(
                        $adapter,
                        (string) $streamData['id'],
                        $fromVersion,
                        $toVersion
                    );

                    $aggregateEventStream = self::restoreEventStream($adapter, $streamData, $streamEventsData);
                }

                return $aggregateEventStream;
            },
            $id,
            $fromVersion,
            $toVersion
        );
    }

    /**
     * {@inheritdoc}
     */
    public function close(AggregateId $id): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(AggregateId $id) use ($adapter): \Generator
            {
                $updateQuery = updateQuery(self::STREAMS_TABLE, ['closed_at' => \date('Y-m-d H:i:s')])
                    ->where(equalsCriteria('id', $id->toString()))
                    ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                $compiledQuery = $updateQuery->compile();

                /** @psalm-suppress MixedTypeCoercion Invalid params() docblock */
                yield $adapter->execute($compiledQuery->sql(), $compiledQuery->params());
            },
            $id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function revert(AggregateId $id, int $toVersion, bool $force): Promise
    {
        $adapter = $this->adapter;

        return call(
            static function(AggregateId $id, int $toVersion, bool $force) use ($adapter): \Generator
            {
                /** @var array<string, string>|null $streamData */
                $streamData = yield from self::doLoadStream($adapter, $id);

                if (null === $streamData)
                {
                    throw EventStreamDoesNotExist::create($id);
                }

                /** @var string $streamId */
                $streamId = $streamData['id'];

                try
                {
                    yield $adapter->transactional(
                        static function(QueryExecutor $queryExecutor) use ($force, $streamId, $toVersion): \Generator
                        {
                            true === $force
                                ? yield from self::doDeleteTailEvents($queryExecutor, $streamId, $toVersion)
                                : yield from self::doSkipEvents($queryExecutor, $streamId, $toVersion);

                            /** restore soft deleted events */
                            yield from self::doRestoreEvents($queryExecutor, $streamId, $toVersion);
                        }
                    );
                }
                catch (UniqueConstraintViolationCheckFailed $exception)
                {
                    throw new EventStreamIntegrityCheckFailed(
                        \sprintf('Error verifying the integrity of the events stream with ID "%s"', $id->toString()),
                        (int) $exception->getCode(),
                        $exception
                    );
                }
            },
            $id,
            $toVersion,
            $force
        );
    }

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doSaveStream(QueryExecutor $queryExecutor, StoredAggregateEventStream $eventsStream): \Generator
    {
        $insertQuery = insertQuery(self::STREAMS_TABLE, [
            'id'               => $eventsStream->aggregateId,
            'identifier_class' => $eventsStream->aggregateIdClass,
            'aggregate_class'  => $eventsStream->aggregateClass,
            'created_at'       => $eventsStream->createdAt,
            'closed_at'        => $eventsStream->closedAt,
        ]);

        $compiledQuery = $insertQuery->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        unset($insertQuery, $compiledQuery, $resultSet);
    }

    /**
     * Saving events in stream.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    private static function doSaveEvents(QueryExecutor $queryExecutor, StoredAggregateEventStream $eventsStream): \Generator
    {
        $eventsCount = \count($eventsStream->storedAggregateEvents);

        if (0 !== $eventsCount)
        {
            /** @psalm-suppress MixedTypeCoercion Invalid params() docblock */
            yield $queryExecutor->execute(
                self::createSaveEventQueryString($eventsCount),
                self::collectSaveEventQueryParameters($eventsStream)
            );
        }
    }

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doLoadStream(QueryExecutor $queryExecutor, AggregateId $id): \Generator
    {
        $selectQuery = selectQuery(self::STREAMS_TABLE)
            ->where(equalsCriteria('id', $id->toString()))
            ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

        /** @var \Latitude\QueryBuilder\Query $compiledQuery */
        $compiledQuery = $selectQuery->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet =  yield $queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-var      array<string, string>|null $data
         *
         * @var array $data
         */
        $data = yield fetchOne($resultSet);

        return $data;
    }

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    private static function doLoadStreamEvents(
        QueryExecutor $queryExecutor,
        string $streamId,
        int $fromVersion,
        ?int $toVersion
    ): \Generator {
        /** @var \Latitude\QueryBuilder\Query\SelectQuery $selectQuery */
        $selectQuery = selectQuery(self::STREAM_EVENTS_TABLE)
            ->where(field('stream_id')->eq($streamId))
            ->andWhere(field('playhead')->gte($fromVersion))
            ->andWhere(field('canceled_at')->isNull());

        if (null !== $toVersion && $fromVersion < $toVersion)
        {
            $selectQuery->andWhere(field('playhead')->lte($toVersion));
        }

        /** @var \Latitude\QueryBuilder\Query $compiledQuery */
        $compiledQuery = $selectQuery->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-var      array<int, array>|null $result
         *
         * @var array $result
         */
        $result = yield fetchAll($resultSet);

        return $result;
    }

    /**
     * Complete removal of the "tail" events from the database
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doDeleteTailEvents(QueryExecutor $executor, string $streamId, int $toVersion): \Generator
    {
        $deleteQuery = deleteQuery(self::STREAM_EVENTS_TABLE)
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->gt($toVersion));

        $compiledQuery = $deleteQuery->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $executor->execute($compiledQuery->sql(), $compiledQuery->params());

        unset($deleteQuery, $compiledQuery, $resultSet);
    }

    /**
     * Soft deletion of events following the specified version
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doSkipEvents(QueryExecutor $executor, string $streamId, int $toVersion): \Generator
    {
        $updateQuery = updateQuery(self::STREAM_EVENTS_TABLE, ['canceled_at' => \date('Y-m-d H:i:s')])
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->gt($toVersion));

        /** @var \Latitude\QueryBuilder\Query $compiledQuery */
        $compiledQuery = $updateQuery->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $executor->execute($compiledQuery->sql(), $compiledQuery->params());

        unset($updateQuery, $compiledQuery, $resultSet);
    }

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doRestoreEvents(QueryExecutor $executor, string $streamId, int $toVersion): \Generator
    {
        $updateQuery = updateQuery(self::STREAM_EVENTS_TABLE, ['canceled_at' => null])
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->lte($toVersion));

        $compiledQuery = $updateQuery->compile();

        /**
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $executor->execute($compiledQuery->sql(), $compiledQuery->params());

        unset($updateQuery, $compiledQuery, $resultSet);
    }

    /**
     * Transform events stream array data to stored representation.
     *
     * @psalm-param array<string, string>  $streamData
     * @psalm-param array<int, array>|null $streamEventsData
     *
     * @throws \LogicException
     */
    private static function restoreEventStream(
        DatabaseAdapter $adapter,
        array $streamData,
        ?array $streamEventsData
    ): StoredAggregateEventStream {
        /** @psalm-var array{
         *     id:string,
         *     identifier_class:class-string<\ServiceBus\EventSourcing\AggregateId>,
         *     aggregate_class:class-string<\ServiceBus\EventSourcing\Aggregate>,
         *     created_at:string,
         *     closed_at:string|null
         * } $streamData
         */

        return new StoredAggregateEventStream(
            $streamData['id'],
            $streamData['identifier_class'],
            $streamData['aggregate_class'],
            self::restoreEvents($adapter, $streamEventsData),
            $streamData['created_at'],
            $streamData['closed_at']
        );
    }

    /**
     * Restore events from rows.
     *
     * @psalm-return array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent>
     *
     * @throws \LogicException
     *
     * @return \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent[]
     */
    private static function restoreEvents(BinaryDataDecoder $decoder, ?array $eventsData): array
    {
        $events = [];

        if (true === \is_array($eventsData) && 0 !== \count($eventsData))
        {
            /**
             * @psalm-var array{
             *   id:string,
             *   playhead:string,
             *   payload:string,
             *   event_class:class-string,
             *   occured_at:string,
             *   recorded_at:string
             * } $eventRow
             */
            foreach ($eventsData as $eventRow)
            {
                $playhead = (int) $eventRow['playhead'];

                $payload = \base64_decode($decoder->unescapeBinary($eventRow['payload']));

                if (true === \is_string($payload))
                {
                    $events[$playhead] = StoredAggregateEvent::restore(
                        $eventRow['id'],
                        $playhead,
                        $payload,
                        $eventRow['event_class'],
                        $eventRow['occured_at'],
                        $eventRow['recorded_at']
                    );

                    continue;
                }

                throw new \LogicException(
                    \sprintf('Unable to decode event content with ID: %s', $eventRow['id'])
                );
            }
        }

        /** @psal-var array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent> */

        return $events;
    }

    /**
     * Create a sql query to store events.
     */
    private static function createSaveEventQueryString(int $eventsCount): string
    {
        return \sprintf(
        /** @lang text */
            'INSERT INTO %s (id, stream_id, playhead, event_class, payload, occured_at, recorded_at) VALUES %s',
            self::STREAM_EVENTS_TABLE,
            \implode(
                ', ',
                \array_fill(0, $eventsCount, '(?, ?, ?, ?, ?, ?, ?)')
            )
        );
    }

    /**
     * Gathering parameters for sending to a request to save events.
     */
    private static function collectSaveEventQueryParameters(StoredAggregateEventStream $eventsStream): array
    {
        $queryParameters = [];
        $rowSetIndex     = 0;

        /** @psalm-var array<int, string|int|float|null> $parameters */
        foreach (self::prepareEventRows($eventsStream) as $parameters)
        {
            foreach ($parameters as $parameter)
            {
                $queryParameters[$rowSetIndex] = $parameter;

                $rowSetIndex++;
            }
        }

        return $queryParameters;
    }

    /**
     * Prepare events to insert.
     *
     * @psalm-return array<int, array<int, string|int>>
     */
    private static function prepareEventRows(StoredAggregateEventStream $eventsStream): array
    {
        $eventsRows = [];

        foreach ($eventsStream->storedAggregateEvents as $storedAggregateEvent)
        {
            /** @var StoredAggregateEvent $storedAggregateEvent */
            $row = [
                $storedAggregateEvent->eventId,
                $eventsStream->aggregateId,
                $storedAggregateEvent->playheadPosition,
                $storedAggregateEvent->eventClass,
                \base64_encode($storedAggregateEvent->eventData),
                $storedAggregateEvent->occuredAt,
                \date('Y-m-d H:i:s'),
            ];

            $eventsRows[] = $row;
        }

        return $eventsRows;
    }
}
