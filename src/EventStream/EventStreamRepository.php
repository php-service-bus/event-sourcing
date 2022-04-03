<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\EventStream;

use ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent;
use ServiceBus\MessageSerializer\ObjectDenormalizer;
use ServiceBus\MessageSerializer\ObjectSerializer;
use Amp\Promise;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream;
use ServiceBus\EventSourcing\Snapshots\Snapshot;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;
use function Amp\call;
use function ServiceBus\Common\createWithoutConstructor;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\datetimeToString;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\jsonDecode;
use function ServiceBus\Common\throwableMessage;

/**
 * Repository for working with event streams.
 */
final class EventStreamRepository
{
    /**
     * @var EventStreamStore
     */
    private $store;

    /**
     * @var ObjectDenormalizer
     */
    private $denormalizer;

    /**
     * @var ObjectSerializer
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

    public function __construct(
        EventStreamStore   $store,
        Snapshotter        $snapshotter,
        ObjectDenormalizer $denormalizer,
        ObjectSerializer   $serializer,
        ?LoggerInterface   $logger = null
    ) {
        $this->store        = $store;
        $this->snapshotter  = $snapshotter;
        $this->denormalizer = $denormalizer;
        $this->serializer   = $serializer;
        $this->logger       = $logger ?? new NullLogger();
    }

    /**
     * Load aggregate.
     *
     * @psalm-return Promise<\ServiceBus\EventSourcing\Aggregate|null>
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function load(AggregateId $id): Promise
    {
        return call(
            function () use ($id): \Generator
            {
                $idValue = $id->toString();
                $idClass = \get_class($id);

                $this->logger->debug('Load aggregate with id "{aggregateIdClass}:{aggregateId}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                ]);

                try
                {
                    $aggregate         = null;
                    $fromStreamVersion = Aggregate::START_PLAYHEAD_INDEX;

                    /** @var \ServiceBus\EventSourcing\Snapshots\Snapshot|null $loadedSnapshot */
                    $loadedSnapshot = yield $this->snapshotter->load($id);

                    if ($loadedSnapshot !== null)
                    {
                        $aggregate         = $loadedSnapshot->aggregate;
                        $fromStreamVersion = $aggregate->version() + 1;

                        $this->logger->debug(
                            'Found a snapshot of the state of the aggregate with the identifier "{aggregateIdClass}:{aggregateId}" on version "{aggregateVersion}"',
                            [
                                'aggregateIdClass' => $idClass,
                                'aggregateId'      => $idValue,
                                'aggregateVersion' => $aggregate->version(),
                            ]
                        );
                    }

                    /** @var \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream|null $storedEventStream */
                    $storedEventStream = yield $this->store->load(
                        id: $id,
                        fromVersion: $fromStreamVersion
                    );

                    return $this->restoreStream(
                        aggregate: $aggregate,
                        storedEventStream: $storedEventStream
                    );
                }
                catch (\Throwable $throwable)
                {
                    $this->logger->debug('Load aggregate with id "{aggregateIdClass}:{aggregateId}" failed', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => throwableMessage($throwable),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
            }
        );
    }

    /**
     * Save a new event stream.
     *
     * @psalm-return Promise<array<int, object>>
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    public function save(Aggregate $aggregate): Promise
    {
        return call(
            function () use ($aggregate): \Generator
            {
                $id = $aggregate->id();

                $idValue = $id->toString();
                $idClass = \get_class($id);

                $this->logger->debug('Save new aggregate with identifier "{aggregateIdClass}:{aggregateId}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                ]);

                try
                {
                    return yield $this->doStore(
                        aggregate: $aggregate,
                        isNew: true
                    );
                }
                catch (\Throwable $throwable)
                {
                    $this->logger->debug('Save new aggregate with identifier "{aggregateIdClass}:{aggregateId}" failed', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => throwableMessage($throwable),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
            }
        );
    }

    /**
     * Update existent event stream (append events).
     *
     * @psalm-return Promise<array<int, object>>
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function update(Aggregate $aggregate): Promise
    {
        return call(
            function () use ($aggregate): \Generator
            {
                $id = $aggregate->id();

                $idValue = $id->toString();
                $idClass = \get_class($id);

                $this->logger->debug('Adding events to an existing stream with identifier "{aggregateIdClass}:{aggregateId}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                ]);

                try
                {
                    return yield $this->doStore(
                        aggregate: $aggregate,
                        isNew: false
                    );
                }
                catch (\Throwable $throwable)
                {
                    $this->logger->debug('Adding events to an existing stream with identifier "{aggregateIdClass}:{aggregateId}', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => throwableMessage($throwable),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
            }
        );
    }

    /**
     * Revert aggregate to specified version.
     *
     * @psalm-return Promise<\ServiceBus\EventSourcing\Aggregate>
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function revert(AggregateId $id, int $toVersion, RevertModeType $mode): Promise
    {
        return call(
            function () use ($id, $toVersion, $mode): \Generator
            {
                $idValue = $id->toString();
                $idClass = \get_class($id);

                $this->logger->debug('Rollback of aggregate with identifier "{aggregateIdClass}:{aggregateId}" to version "{aggregateVersion}"', [
                    'aggregateIdClass' => $idClass,
                    'aggregateId'      => $idValue,
                    'aggregateVersion' => $toVersion,
                ]);

                try
                {
                    yield $this->store->revert(
                        id: $id,
                        toVersion: $toVersion,
                        force: RevertModeType::DELETE === $mode
                    );

                    /** @var StoredAggregateEventStream|null $storedEventStream */
                    $storedEventStream = yield $this->store->load($id);

                    /** @var Aggregate $aggregate */
                    $aggregate = $this->restoreStream(
                        aggregate: null,
                        storedEventStream: $storedEventStream
                    );

                    yield $this->snapshotter->store(new Snapshot($aggregate, $aggregate->version()));

                    return $aggregate;
                }
                catch (\Throwable $throwable)
                {
                    $this->logger->debug('Error when rolling back the version of the aggregate with the identifier "{aggregateIdClass}:{aggregateId}', [
                        'aggregateIdClass' => $idClass,
                        'aggregateId'      => $idValue,
                        'throwableMessage' => throwableMessage($throwable),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                    ]);

                    throw $throwable;
                }
            }
        );
    }

    /**
     * @psalm-return Promise<array<int, object>>
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    private function doStore(Aggregate $aggregate, bool $isNew): Promise
    {
        return call(
            function () use ($aggregate, $isNew): \Generator
            {
                /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $eventStream */
                $eventStream    = invokeReflectionMethod($aggregate, 'makeStream');
                $receivedEvents = $eventStream->originEvents;

                $storedEventStream = \array_map(
                    function (AggregateEvent $aggregateEvent): StoredAggregateEvent
                    {
                        /** @psalm-var class-string $eventClass */
                        $eventClass = \get_class($aggregateEvent->event);

                        return StoredAggregateEvent::create(
                            eventId: $aggregateEvent->id,
                            playheadPosition: $aggregateEvent->playhead,
                            eventData: $this->serializer->encode($aggregateEvent->event),
                            eventClass: $eventClass,
                            occurredAt: $aggregateEvent->occurredAt->format('Y-m-d H:i:s.u')
                        );
                    },
                    $eventStream->events
                );

                /** @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $eventClass */
                $eventClass = \get_class($eventStream->id);

                $storedEventStream = new StoredAggregateEventStream(
                    aggregateId: $eventStream->id->toString(),
                    aggregateIdClass: $eventClass,
                    aggregateClass: $eventStream->aggregateClass,
                    storedAggregateEvents: $storedEventStream,
                    createdAt: (string) datetimeToString($eventStream->createdAt)
                );

                /** @noinspection PhpUnnecessaryLocalVariableInspection */
                $promise = $isNew
                    ? $this->store->save($storedEventStream)
                    : $this->store->append($storedEventStream);

                yield $promise;

                /** @var \ServiceBus\EventSourcing\Snapshots\Snapshot|null $loadedSnapshot */
                $loadedSnapshot = yield $this->snapshotter->load($aggregate->id());

                if ($this->snapshotter->snapshotMustBeCreated($aggregate, $loadedSnapshot))
                {
                    yield $this->snapshotter->store(new Snapshot($aggregate, $aggregate->version()));
                }

                return $receivedEvents;
            }
        );
    }

    /**
     * Restore the aggregate from the event stream/Add missing events to the aggregate from the snapshot.
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     */
    private function restoreStream(?Aggregate $aggregate, ?StoredAggregateEventStream $storedEventStream): ?Aggregate
    {
        if ($storedEventStream === null)
        {
            return null;
        }

        $events = \array_map(
            function (StoredAggregateEvent $storedAggregateEvent): AggregateEvent
            {
                /** @var \DateTimeImmutable $occurredAt */
                $occurredAt = datetimeInstantiator($storedAggregateEvent->occurredAt);

                /** @var \DateTimeImmutable $recordedAt */
                $recordedAt = datetimeInstantiator($storedAggregateEvent->recordedAt);

                $event = $this->backwardCompatibilityDecoder(
                    messagePayload: $storedAggregateEvent->eventData,
                    toClass: $storedAggregateEvent->eventClass
                );

                return AggregateEvent::restore(
                    id: $storedAggregateEvent->eventId,
                    event: $event,
                    playhead: $storedAggregateEvent->playheadPosition,
                    occuredAt: $occurredAt,
                    recordedAt: $recordedAt
                );
            },
            $storedEventStream->storedAggregateEvents
        );

        /** @var \DateTimeImmutable $createdAt */
        $createdAt = datetimeInstantiator($storedEventStream->createdAt);

        /** @var \DateTimeImmutable|null $closedAt */
        $closedAt = datetimeInstantiator($storedEventStream->closedAt);

        /** @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $idClass */
        $idClass = $storedEventStream->aggregateIdClass;

        /** @var AggregateId $id */
        $id = new $idClass($storedEventStream->aggregateId);

        $eventStream = new AggregateEventStream(
            id: $id,
            aggregateClass: $storedEventStream->aggregateClass,
            events: $events,
            createdAt: $createdAt,
            closedAt: $closedAt
        );

        if ($aggregate === null)
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

    /**
     * Since the format of the saved messages has changed, you need to add backward compatibility support.
     *
     * @template T of object
     *
     * @psalm-param non-empty-string $messagePayload
     * @psalm-param class-string<T>  $toClass
     *
     * @psalm-return T
     */
    private function backwardCompatibilityDecoder(string $messagePayload, string $toClass): object
    {
        $data = jsonDecode($messagePayload);

        if (\array_key_exists('message', $data) && \array_key_exists('namespace', $data))
        {
            /** @psalm-var array{message: array<array-key, mixed>, namespace: class-string<T>} $oldFormat */
            $oldFormat = $data;

            return $this->denormalizer->handle(
                payload: $oldFormat['message'],
                objectClass: $oldFormat['namespace']
            );
        }

        return $this->denormalizer->handle(
            payload: $data,
            objectClass: $toClass
        );
    }
}
