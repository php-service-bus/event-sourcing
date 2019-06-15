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
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\EventStream\Serializer\EventSerializer;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream;
use ServiceBus\EventSourcing\Snapshots\Snapshot;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;

/**
 *
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
     * @param EventStreamStore $store
     * @param Snapshotter      $snapshotter
     * @param EventSerializer  $serializer
     */
    public function __construct(EventStreamStore $store, Snapshotter $snapshotter, EventSerializer $serializer)
    {
        $this->store       = $store;
        $this->snapshotter = $snapshotter;
        $this->serializer  = $serializer;
    }

    /**
     * Load aggregate.
     *
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * @param AggregateId $id
     *
     * @return Promise<\ServiceBus\EventSourcing\Aggregate|null>
     */
    public function load(AggregateId $id): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(AggregateId $id): \Generator
            {
                $aggregate         = null;
                $fromStreamVersion = Aggregate::START_PLAYHEAD_INDEX;

                /** @var \ServiceBus\EventSourcing\Snapshots\Snapshot|null $loadedSnapshot */
                $loadedSnapshot = yield $this->snapshotter->load($id);

                if (null !== $loadedSnapshot)
                {
                    $aggregate         = $loadedSnapshot->aggregate;
                    $fromStreamVersion = $aggregate->version() + 1;
                }

                /** @var \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream|null $storedEventStream */
                $storedEventStream = yield $this->store->load($id, $fromStreamVersion);

                $aggregate = $this->restoreStream($aggregate, $storedEventStream);

                unset($storedEventStream, $loadedSnapshot, $fromStreamVersion);

                return $aggregate;
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
     * @return Promise<array<int, object>>
     */
    public function save(Aggregate $aggregate): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Aggregate $aggregate): \Generator
            {
                /**
                 * @psalm-var array<int, object> $raisedEvents
                 *
                 * @var object[] $raisedEvents
                 */
                $raisedEvents = yield from $this->doStore($aggregate, true);

                return $raisedEvents;
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
     * @return Promise<array<int, object>>
     */
    public function update(Aggregate $aggregate): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Aggregate $aggregate): \Generator
            {
                /**
                 * @psalm-var array<int, object> $raisedEvents
                 *
                 * @var object[] $raisedEvents
                 */
                $raisedEvents = yield from $this->doStore($aggregate, false);

                return $raisedEvents;
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
     * @return Promise<\ServiceBus\EventSourcing\Aggregate>
     */
    public function revert(Aggregate $aggregate, int $toVersion, int $mode = self::REVERT_MODE_SOFT_DELETE): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Aggregate $aggregate, int $toVersion, int $mode): \Generator
            {
                yield $this->store->revert(
                    $aggregate->id(),
                    $toVersion,
                    self::REVERT_MODE_DELETE === $mode
                );

                /** @var StoredAggregateEventStream|null $storedEventStream */
                $storedEventStream = yield $this->store->load($aggregate->id());

                /** @var Aggregate $aggregate */
                $aggregate = $this->restoreStream(null, $storedEventStream);

                yield $this->snapshotter->store(Snapshot::create($aggregate, $aggregate->version()));

                return $aggregate;
            },
            $aggregate,
            $toVersion,
            $mode
        );
    }

    /**
     * @param Aggregate $aggregate
     * @param bool      $isNew
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
    private function doStore(Aggregate $aggregate, bool $isNew): \Generator
    {
        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $eventStream */
        $eventStream    = invokeReflectionMethod($aggregate, 'makeStream');
        $receivedEvents = $eventStream->originEvents;

        $storedEventStream = streamToStoredRepresentation($this->serializer, $eventStream);

        $promise = true === $isNew
            ? $this->store->save($storedEventStream)
            : $this->store->append($storedEventStream);

        yield $promise;

        /** @var \ServiceBus\EventSourcing\Snapshots\Snapshot|null $loadedSnapshot */
        $loadedSnapshot = yield $this->snapshotter->load($aggregate->id());

        if (true === $this->snapshotter->snapshotMustBeCreated($aggregate, $loadedSnapshot))
        {
            yield $this->snapshotter->store(Snapshot::create($aggregate, $aggregate->version()));
        }

        unset($eventStream, $loadedSnapshot, $storedEventStream);

        return $receivedEvents;
    }

    /**
     * Restore the aggregate from the event stream/Add missing events to the aggregate from the snapshot.
     *
     * @param Aggregate|null                  $aggregate
     * @param StoredAggregateEventStream|null $storedEventStream
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     *
     * @return Aggregate|null
     */
    private function restoreStream(?Aggregate $aggregate, ?StoredAggregateEventStream $storedEventStream): ?Aggregate
    {
        if (null === $storedEventStream)
        {
            return null;
        }

        $eventStream = streamToDomainRepresentation($this->serializer, $storedEventStream);

        if (null === $aggregate)
        {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            /** @var Aggregate $aggregate */
            $aggregate = createWithoutConstructor($storedEventStream->aggregateClass);
        }

        invokeReflectionMethod($aggregate, 'appendStream', $eventStream);

        return $aggregate;
    }
}
