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

    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @param DatabaseAdapter $adapter
     */
    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function save(StoredAggregateEventStream $aggregateEventStream): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(StoredAggregateEventStream $aggregateEventStream) use ($adapter): \Generator
            {
                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 *
                 * @var \ServiceBus\Storage\Common\Transaction $transaction
                 */
                $transaction = yield $adapter->transaction();

                try
                {
                    yield from self::doSaveStream($transaction, $aggregateEventStream);
                    yield from self::doSaveEvents($transaction, $aggregateEventStream);

                    yield $transaction->commit();
                }
                catch (\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
                finally
                {
                    unset($transaction);
                }
            },
            $aggregateEventStream
        );
    }

    /**
     * {@inheritdoc}
     */
    public function append(StoredAggregateEventStream $aggregateEventStream): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(StoredAggregateEventStream $aggregateEventStream) use ($adapter): \Generator
            {
                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 *
                 * @var \ServiceBus\Storage\Common\Transaction $transaction
                 */
                $transaction = yield $adapter->transaction();

                try
                {
                    yield from self::doSaveEvents($transaction, $aggregateEventStream);

                    yield $transaction->commit();
                }
                catch (\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
                finally
                {
                    unset($transaction);
                }
            },
            $aggregateEventStream
        );
    }

    /**
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * {@inheritdoc}
     */
    public function load(AggregateId $id, int $fromVersion = Aggregate::START_PLAYHEAD_INDEX, ?int $toVersion = null): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
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

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id) use ($adapter): \Generator
            {
                $updateQuery = updateQuery(self::STREAMS_TABLE, ['closed_at' => \date('Y-m-d H:i:s')])
                    ->where(equalsCriteria('id', $id->toString()))
                    ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                $compiledQuery = $updateQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 */
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

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
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

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 *
                 * @var \ServiceBus\Storage\Common\Transaction $transaction
                 */
                $transaction = yield $adapter->transaction();

                try
                {
                    true === $force
                        ? yield from self::doDeleteTailEvents($transaction, $streamId, $toVersion)
                        : yield from self::doSkipEvents($transaction, $streamId, $toVersion);

                    /** restore soft deleted events */
                    yield from self::doRestoreEvents($transaction, $streamId, $toVersion);

                    yield $transaction->commit();
                }
                catch (UniqueConstraintViolationCheckFailed $exception)
                {
                    yield $transaction->rollback();

                    throw new EventStreamIntegrityCheckFailed(
                        \sprintf('Error verifying the integrity of the events stream with ID "%s"', $id->toString()),
                        (int) $exception->getCode(),
                        $exception
                    );
                }
                catch (\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
                finally
                {
                    unset($transaction);
                }
            },
            $id,
            $toVersion,
            $force
        );
    }

    /**
     * @param QueryExecutor              $queryExecutor
     * @param StoredAggregateEventStream $eventsStream
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return \Generator
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
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
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
     * @param QueryExecutor              $queryExecutor
     * @param StoredAggregateEventStream $eventsStream
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     *
     * @return \Generator
     */
    private static function doSaveEvents(QueryExecutor $queryExecutor, StoredAggregateEventStream $eventsStream): \Generator
    {
        $eventsCount = \count($eventsStream->storedAggregateEvents);

        if (0 !== $eventsCount)
        {
            /**
             * @psalm-suppress TooManyTemplateParams Wrong Promise template
             * @psalm-suppress MixedTypeCoercion Invalid params() docblock
             */
            yield $queryExecutor->execute(
                self::createSaveEventQueryString($eventsCount),
                self::collectSaveEventQueryParameters($eventsStream)
            );
        }
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param QueryExecutor $queryExecutor
     * @param AggregateId   $id
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return \Generator
     */
    private static function doLoadStream(QueryExecutor $queryExecutor, AggregateId $id): \Generator
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $selectQuery = selectQuery(self::STREAMS_TABLE)
            ->where(equalsCriteria('id', $id->toString()))
            ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

        /** @var \Latitude\QueryBuilder\Query $compiledQuery */
        $compiledQuery = $selectQuery->compile();

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = /** @noinspection PhpUnhandledExceptionInspection */
            yield $queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-var      array<string, string>|null $data
         *
         * @var array $data
         */
        $data = /** @noinspection PhpUnhandledExceptionInspection */
            yield fetchOne($resultSet);

        return $data;
    }

    /**
     * @param QueryExecutor $queryExecutor
     * @param string        $streamId
     * @param int           $fromVersion
     * @param int|null      $toVersion
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     *
     * @return \Generator
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
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = yield $queryExecutor->execute($compiledQuery->sql(), $compiledQuery->params());

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-var      array<int, array>|null $result
         *
         * @var array $result
         */
        $result = yield fetchAll($resultSet);

        return $result;
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * Complete removal of the "tail" events from the database
     *
     * @param QueryExecutor $executor
     * @param string        $streamId
     * @param int           $toVersion
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return \Generator
     */
    private static function doDeleteTailEvents(QueryExecutor $executor, string $streamId, int $toVersion): \Generator
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $deleteQuery = deleteQuery(self::STREAM_EVENTS_TABLE)
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->gt($toVersion));

        $compiledQuery = $deleteQuery->compile();

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = /** @noinspection PhpUnhandledExceptionInspection */
            yield $executor->execute($compiledQuery->sql(), $compiledQuery->params());

        unset($deleteQuery, $compiledQuery, $resultSet);
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * Soft deletion of events following the specified version
     *
     * @param QueryExecutor $executor
     * @param string        $streamId
     * @param int           $toVersion
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return \Generator
     */
    private static function doSkipEvents(QueryExecutor $executor, string $streamId, int $toVersion): \Generator
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $updateQuery = updateQuery(self::STREAM_EVENTS_TABLE, ['canceled_at' => \date('Y-m-d H:i:s')])
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->gt($toVersion));

        /** @var \Latitude\QueryBuilder\Query $compiledQuery */
        $compiledQuery = $updateQuery->compile();

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
         * @psalm-suppress MixedTypeCoercion Invalid params() docblock
         *
         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
         */
        $resultSet = /** @noinspection PhpUnhandledExceptionInspection */
            yield $executor->execute($compiledQuery->sql(), $compiledQuery->params());

        unset($updateQuery, $compiledQuery, $resultSet);
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param QueryExecutor $executor
     * @param string        $streamId
     * @param int           $toVersion
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return \Generator
     */
    private static function doRestoreEvents(QueryExecutor $executor, string $streamId, int $toVersion): \Generator
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $updateQuery = updateQuery(self::STREAM_EVENTS_TABLE, ['canceled_at' => null])
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->lte($toVersion));

        $compiledQuery = $updateQuery->compile();

        /**
         * @psalm-suppress TooManyTemplateParams Wrong Promise template
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
     * @param DatabaseAdapter $adapter
     * @param array           $streamData
     * @param array           $streamEventsData
     *
     * @throws \LogicException
     *
     * @return StoredAggregateEventStream
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

        return StoredAggregateEventStream::create(
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
     * @param BinaryDataDecoder $decoder
     * @param array|null        $eventsData
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
     *
     * @param int $eventsCount
     *
     * @return string
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
     *
     * @param StoredAggregateEventStream $eventsStream
     *
     * @return array
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
     *
     * @param StoredAggregateEventStream $eventsStream
     *
     * @return array
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
