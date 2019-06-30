<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream;

use function Amp\call;
use function ServiceBus\Common\createWithoutConstructor;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\EventSourcing\EventStream\Store\streamToDomainRepresentation;
use function ServiceBus\EventSourcing\EventStream\Store\streamToStoredRepresentation;
use Amp\Promise;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\EventStream\Serializer\EventSerializer;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream;
use ServiceBus\EventSourcing\Snapshots\Snapshot;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;

/**
 * Repository for working with event streams.
 */
final class EventStreamRepository
{
    public const REVERT_MODE_SOFT_DELETE = 1;

    public const REVERT_MODE_DELETE = 2;

    /**
     * @var EventStreamStore
     */
    private $store;

    /**
     * @var EventSerializer
     */
    private $serializer;

    /**
     * @var Snapshotter
     */
    private $snapshotter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EventStreamStore     $store
     * @param Snapshotter          $snapshotter
     * @param EventSerializer      $serializer
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        EventStreamStore $store,
        Snapshotter $snapshotter,
        EventSerializer $serializer,
        ?LoggerInterface $logger = null
    ) {
        $this->store       = $store;
        $this->snapshotter = $snapshotter;
        $this->serializer  = $serializer;
        $this->logger      = $logger ?? new NullLogger();
    }

    /**
     * Load aggregate.
     *
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * @param AggregateId $id
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return Promise<\ServiceBus\EventSourcing\Aggregate|null>
     */
    public function load(AggregateId $id): Promise
    {
        $store      = $this->store;
        $serializer = $this->serializer;
        $snaphotter = $this->snapshotter;
        $logger     = $this->logger;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id) use ($store, $serializer, $snaphotter, $logger): \Generator
            {
                $idValue = $id->toString();
                $idClass = \get_class($id);

                $logger->debug('Load aggregate with id "{aggregateIdClass}:{aggregateId}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                ]);

                try
                {
                    $aggregate         = null;
                    $fromStreamVersion = Aggregate::START_PLAYHEAD_INDEX;

                    /** @var \ServiceBus\EventSourcing\Snapshots\Snapshot|null $loadedSnapshot */
                    $loadedSnapshot = yield $snaphotter->load($id);

                    if (null !== $loadedSnapshot)
                    {
                        $aggregate         = $loadedSnapshot->aggregate;
                        $fromStreamVersion = $aggregate->version() + 1;

                        $logger->debug(
                            'Found a snapshot of the state of the aggregate with the identifier "{aggregateIdClass}:{aggregateId}" on version "{aggregateVersion}"',
                            [
                                'aggregateIdClass' => $idClass,
                                'aggregateId'      => $idValue,
                                'aggregateVersion' => $aggregate->version(),
                            ]
                        );
                    }

                    /** @var \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream|null $storedEventStream */
                    $storedEventStream = yield $store->load($id, $fromStreamVersion);

                    $aggregate = self::restoreStream($serializer, $aggregate, $storedEventStream);

                    return $aggregate;
                }
                catch (\Throwable $throwable)
                {
                    $logger->debug('Load aggregate with id "{aggregateIdClass}:{aggregateId}" failed', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => $throwable->getMessage(),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
                finally
                {
                    unset($storedEventStream, $loadedSnapshot, $fromStreamVersion);
                }
            },
            $id
        );
    }

    /**
     * Save a new event stream.
     *
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * @param Aggregate $aggregate
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     *
     * @return Promise<array<int, object>>
     */
    public function save(Aggregate $aggregate): Promise
    {
        $store      = $this->store;
        $serializer = $this->serializer;
        $snaphotter = $this->snapshotter;
        $logger     = $this->logger;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(Aggregate $aggregate) use ($store, $serializer, $snaphotter, $logger): \Generator
            {
                $id = $aggregate->id();

                $idValue = $id->toString();
                $idClass = \get_class($id);

                $logger->debug('Save new aggregate with identifier "{aggregateIdClass}:{aggregateId}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                ]);

                try
                {
                    /**
                     * @psalm-var array<int, object> $raisedEvents
                     *
                     * @var object[] $raisedEvents
                     */
                    $raisedEvents = yield from self::doStore($serializer, $store, $snaphotter, $aggregate, true);

                    return $raisedEvents;
                }
                catch (\Throwable $throwable)
                {
                    $logger->debug('Save new aggregate with identifier "{aggregateIdClass}:{aggregateId}" failed', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => $throwable->getMessage(),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
            },
            $aggregate
        );
    }

    /**
     * Update existent event stream (append events).
     *
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * @param Aggregate $aggregate
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return Promise<array<int, object>>
     */
    public function update(Aggregate $aggregate): Promise
    {
        $store      = $this->store;
        $serializer = $this->serializer;
        $snaphotter = $this->snapshotter;
        $logger     = $this->logger;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(Aggregate $aggregate) use ($store, $serializer, $snaphotter, $logger): \Generator
            {
                $id = $aggregate->id();

                $idValue = $id->toString();
                $idClass = \get_class($id);

                $logger->debug('Adding events to an existing stream with identifier "{aggregateIdClass}:{aggregateId}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                ]);

                try
                {
                    /**
                     * @psalm-var array<int, object> $raisedEvents
                     *
                     * @var object[] $raisedEvents
                     */
                    $raisedEvents = yield from self::doStore($serializer, $store, $snaphotter, $aggregate, false);

                    return $raisedEvents;
                }
                catch (\Throwable $throwable)
                {
                    $logger->debug('Adding events to an existing stream with identifier "{aggregateIdClass}:{aggregateId}', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => $throwable->getMessage(),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
            },
            $aggregate
        );
    }

    /**
     * Revert aggregate to specified version.
     *
     * Mode options:
     *   - 1 (self::REVERT_MODE_SOFT_DELETE): Mark tail events as deleted (soft deletion). There may be version
     *   conflicts in some situations
     *   - 2 (self::REVERT_MODE_DELETE): Removes tail events from the database (the best option)
     *
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * @param Aggregate $aggregate
     * @param int       $toVersion
     * @param int       $mode
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     *
     * @return Promise<\ServiceBus\EventSourcing\Aggregate>
     */
    public function revert(Aggregate $aggregate, int $toVersion, int $mode = self::REVERT_MODE_SOFT_DELETE): Promise
    {
        $store      = $this->store;
        $serializer = $this->serializer;
        $snaphotter = $this->snapshotter;
        $logger     = $this->logger;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(Aggregate $aggregate, int $toVersion, int $mode) use ($store, $serializer, $snaphotter, $logger): \Generator
            {
                $id = $aggregate->id();

                $idValue = $id->toString();
                $idClass = \get_class($id);

                $logger->debug('Rollback of aggregate with identifier "{aggregateIdClass}:{aggregateId}" to version "{aggregateVersion}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                    'aggregateVersion' => $toVersion,
                ]);

                try
                {
                    yield $store->revert($aggregate->id(), $toVersion, self::REVERT_MODE_DELETE === $mode);

                    /** @var StoredAggregateEventStream|null $storedEventStream */
                    $storedEventStream = yield $store->load($aggregate->id());

                    /** @var Aggregate $aggregate */
                    $aggregate = self::restoreStream($serializer, null, $storedEventStream);

                    yield $snaphotter->store(Snapshot::create($aggregate, $aggregate->version()));

                    return $aggregate;
                }
                catch (\Throwable $throwable)
                {
                    $logger->debug('Error when rolling back the version of the aggregate with the identifier "{aggregateIdClass}:{aggregateId}', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => $throwable->getMessage(),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
                finally
                {
                    unset($storedEventStream);
                }
            },
            $aggregate,
            $toVersion,
            $mode
        );
    }

    /**
     * @param EventSerializer  $eventSerializer
     * @param EventStreamStore $eventStreamStore
     * @param Snapshotter      $snapshotter
     * @param Aggregate        $aggregate
     * @param bool             $isNew
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     *
     * @return \Generator
     */
    private static function doStore(
        EventSerializer $eventSerializer,
        EventStreamStore $eventStreamStore,
        Snapshotter $snapshotter,
        Aggregate $aggregate,
        bool $isNew
    ): \Generator {
        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $eventStream */
        $eventStream    = invokeReflectionMethod($aggregate, 'makeStream');
        $receivedEvents = $eventStream->originEvents;

        $storedEventStream = streamToStoredRepresentation($eventSerializer, $eventStream);

        $promise = true === $isNew
            ? $eventStreamStore->save($storedEventStream)
            : $eventStreamStore->append($storedEventStream);

        yield $promise;

        /** @var \ServiceBus\EventSourcing\Snapshots\Snapshot|null $loadedSnapshot */
        $loadedSnapshot = yield $snapshotter->load($aggregate->id());

        if (true === $snapshotter->snapshotMustBeCreated($aggregate, $loadedSnapshot))
        {
            yield $snapshotter->store(Snapshot::create($aggregate, $aggregate->version()));
        }

        unset($eventStream, $loadedSnapshot, $storedEventStream);

        return $receivedEvents;
    }

    /**
     * Restore the aggregate from the event stream/Add missing events to the aggregate from the snapshot.
     *
     * @param EventSerializer                 $eventSerializer
     * @param Aggregate|null                  $aggregate
     * @param StoredAggregateEventStream|null $storedEventStream
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     *
     * @return Aggregate|null
     */
    private static function restoreStream(
        EventSerializer $eventSerializer,
        ?Aggregate $aggregate,
        ?StoredAggregateEventStream $storedEventStream
    ): ?Aggregate {
        if (null === $storedEventStream)
        {
            return null;
        }

        $eventStream = streamToDomainRepresentation($eventSerializer, $storedEventStream);

        if (null === $aggregate)
        {
            /**
             * @noinspection CallableParameterUseCaseInTypeContextInspection
             *
             * @var Aggregate $aggregate
             */
            $aggregate = createWithoutConstructor($storedEventStream->aggregateClass);
        }

        invokeReflectionMethod($aggregate, 'appendStream', $eventStream);

        return $aggregate;
    }
}
