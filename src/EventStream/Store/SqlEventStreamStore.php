<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\EventSourcing\EventStream\Store;

use function Amp\call;
use function Latitude\QueryBuilder\field;
use function ServiceBus\Common\now;
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

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function save(StoredAggregateEventStream $aggregateEventStream): Promise
    {
        return $this->adapter->transactional(
            static function (QueryExecutor $queryExecutor) use ($aggregateEventStream): \Generator
            {
                yield from self::doSaveStream($queryExecutor, $aggregateEventStream);
                yield from self::doSaveEvents($queryExecutor, $aggregateEventStream);
            }
        );
    }

    public function append(StoredAggregateEventStream $aggregateEventStream): Promise
    {
        return $this->adapter->transactional(
            static function (QueryExecutor $queryExecutor) use ($aggregateEventStream): \Generator
            {
                yield from self::doSaveEvents($queryExecutor, $aggregateEventStream);
            }
        );
    }

    public function load(
        AggregateId $id,
        int $fromVersion = Aggregate::START_PLAYHEAD_INDEX,
        ?int $toVersion = null
    ): Promise {
        return call(
            function () use ($id, $fromVersion, $toVersion): \Generator
            {
                $aggregateEventStream = null;

                /**
                 * @psalm-var  array{
                 *     id:string,
                 *     identifier_class:class-string<\ServiceBus\EventSourcing\AggregateId>,
                 *     aggregate_class:class-string<\ServiceBus\EventSourcing\Aggregate>,
                 *     created_at:string,
                 *     closed_at:string|null
                 * }|null $streamData
                 */
                $streamData = yield from self::doLoadStream(
                    queryExecutor: $this->adapter,
                    id: $id
                );

                if ($streamData !== null)
                {
                    /** @var array<int, array>|null $streamEventsData */
                    $streamEventsData = yield from self::doLoadStreamEvents(
                        queryExecutor: $this->adapter,
                        streamId: $streamData['id'],
                        fromVersion: $fromVersion,
                        toVersion: $toVersion
                    );

                    $aggregateEventStream = self::restoreEventStream(
                        queryExecutor: $this->adapter,
                        streamData: $streamData,
                        streamEventsData: $streamEventsData
                    );
                }

                return $aggregateEventStream;
            }
        );
    }

    public function close(AggregateId $id): Promise
    {
        return call(
            function () use ($id): \Generator
            {
                $updateQuery = updateQuery(self::STREAMS_TABLE, ['closed_at' => \date('Y-m-d H:i:s')])
                    ->where(equalsCriteria('id', $id->toString()))
                    ->andWhere(equalsCriteria('identifier_class', \get_class($id)));

                $compiledQuery = $updateQuery->compile();

                /** @psalm-suppress MixedArgumentTypeCoercion */
                yield $this->adapter->execute(
                    queryString: $compiledQuery->sql(),
                    parameters: $compiledQuery->params()
                );
            }
        );
    }

    public function revert(AggregateId $id, int $toVersion, bool $force): Promise
    {
        return call(
            function () use ($id, $toVersion, $force): \Generator
            {
                /** @var array<string, string>|null $streamData */
                $streamData = yield from self::doLoadStream(
                    queryExecutor: $this->adapter,
                    id: $id
                );

                if ($streamData === null)
                {
                    throw EventStreamDoesNotExist::create($id);
                }

                /** @var string $streamId */
                $streamId = $streamData['id'];

                try
                {
                    yield $this->adapter->transactional(
                        static function (QueryExecutor $queryExecutor) use ($force, $streamId, $toVersion): \Generator
                        {
                            $force
                                ?
                                yield from self::doDeleteTailEvents(
                                    queryExecutor: $queryExecutor,
                                    streamId: $streamId,
                                    toVersion: $toVersion
                                )
                                :
                                yield from self::doSkipEvents(
                                    queryExecutor: $queryExecutor,
                                    streamId: $streamId,
                                    toVersion: $toVersion
                                );

                            /** restore soft deleted events */
                            yield from self::doRestoreEvents(
                                queryExecutor: $queryExecutor,
                                streamId: $streamId,
                                toVersion: $toVersion
                            );
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
            }
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

        /** @psalm-suppress MixedArgumentTypeCoercion */
        yield $queryExecutor->execute(
            queryString: $compiledQuery->sql(),
            parameters: $compiledQuery->params()
        );
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

        if ($eventsCount !== 0)
        {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            yield $queryExecutor->execute(
                queryString: self::createSaveEventQueryString($eventsCount),
                parameters: self::collectSaveEventQueryParameters($eventsStream)
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

        $compiledQuery = $selectQuery->compile();

        /** @psalm-suppress MixedArgumentTypeCoercion */
        $resultSet = yield $queryExecutor->execute(
            queryString: $compiledQuery->sql(),
            parameters: $compiledQuery->params()
        );

        return yield fetchOne($resultSet);
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
        $selectQuery = selectQuery(self::STREAM_EVENTS_TABLE)
            ->where(field('stream_id')->eq($streamId))
            ->andWhere(field('playhead')->gte($fromVersion))
            ->andWhere(field('canceled_at')->isNull());

        if ($toVersion !== null && $fromVersion < $toVersion)
        {
            $selectQuery->andWhere(field('playhead')->lte($toVersion));
        }

        $compiledQuery = $selectQuery->compile();

        /** @psalm-suppress MixedArgumentTypeCoercion */
        $resultSet = yield $queryExecutor->execute(
            queryString: $compiledQuery->sql(),
            parameters: $compiledQuery->params()
        );

        return yield fetchAll($resultSet);
    }

    /**
     * Complete removal of the "tail" events from the database.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doDeleteTailEvents(
        QueryExecutor $queryExecutor,
        string $streamId,
        int $toVersion
    ): \Generator {
        $deleteQuery = deleteQuery(self::STREAM_EVENTS_TABLE)
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->gt($toVersion));

        $compiledQuery = $deleteQuery->compile();

        /** @psalm-suppress MixedArgumentTypeCoercion */
        yield $queryExecutor->execute(
            queryString: $compiledQuery->sql(),
            parameters: $compiledQuery->params()
        );
    }

    /**
     * Soft deletion of events following the specified version.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doSkipEvents(QueryExecutor $queryExecutor, string $streamId, int $toVersion): \Generator
    {
        $updateQuery = updateQuery(self::STREAM_EVENTS_TABLE, ['canceled_at' => \date('Y-m-d H:i:s')])
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->gt($toVersion));

        $compiledQuery = $updateQuery->compile();

        /** @psalm-suppress MixedArgumentTypeCoercion */
        yield $queryExecutor->execute(
            queryString: $compiledQuery->sql(),
            parameters: $compiledQuery->params()
        );
    }

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    private static function doRestoreEvents(QueryExecutor $queryExecutor, string $streamId, int $toVersion): \Generator
    {
        $updateQuery = updateQuery(self::STREAM_EVENTS_TABLE, ['canceled_at' => null])
            ->where(equalsCriteria('stream_id', $streamId))
            ->andWhere(field('playhead')->lte($toVersion));

        $compiledQuery = $updateQuery->compile();

        /** @psalm-suppress MixedArgumentTypeCoercion */
        yield $queryExecutor->execute(
            queryString: $compiledQuery->sql(),
            parameters: $compiledQuery->params()
        );
    }

    /**
     * Transform events stream array data to stored representation.
     *
     * @psalm-param  array{
     *     id:string,
     *     identifier_class:class-string<\ServiceBus\EventSourcing\AggregateId>,
     *     aggregate_class:class-string<\ServiceBus\EventSourcing\Aggregate>,
     *     created_at:string,
     *     closed_at:string|null
     * } $streamData
     * @psalm-param array<int, array>|null $streamEventsData
     *
     * @throws \LogicException
     */
    private static function restoreEventStream(
        QueryExecutor $queryExecutor,
        array $streamData,
        ?array $streamEventsData
    ): StoredAggregateEventStream {
        /** @var DatabaseAdapter $queryExecutor */

        return new StoredAggregateEventStream(
            aggregateId: $streamData['id'],
            aggregateIdClass: $streamData['identifier_class'],
            aggregateClass: $streamData['aggregate_class'],
            storedAggregateEvents: self::restoreEvents($queryExecutor, $streamEventsData),
            createdAt: $streamData['created_at'],
            closedAt: $streamData['closed_at']
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

        if (\is_array($eventsData) && \count($eventsData) !== 0)
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
                $payload  = (string) \base64_decode($decoder->unescapeBinary($eventRow['payload']));

                $events[$playhead] = StoredAggregateEvent::restore(
                    eventId: $eventRow['id'],
                    playheadPosition: $playhead,
                    eventData: $payload,
                    eventClass: $eventRow['event_class'],
                    occurredAt: $eventRow['occured_at'],
                    recordedAt: $eventRow['recorded_at']
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

        /** @var StoredAggregateEvent $storedAggregateEvent */
        foreach ($eventsStream->storedAggregateEvents as $storedAggregateEvent)
        {
            $row = [
                $storedAggregateEvent->eventId,
                $eventsStream->aggregateId,
                $storedAggregateEvent->playheadPosition,
                $storedAggregateEvent->eventClass,
                \base64_encode($storedAggregateEvent->eventData),
                $storedAggregateEvent->occuredAt,
                now()->format('Y-m-d H:i:s.u'),
            ];

            $eventsRows[] = $row;
        }

        return $eventsRows;
    }
}
