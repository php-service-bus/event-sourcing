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

use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\datetimeToString;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\EventStream\AggregateEvent;
use ServiceBus\EventSourcing\EventStream\AggregateEventStream;
use ServiceBus\EventSourcing\EventStream\Serializer\EventSerializer;

/**
 * @internal
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
 */
function streamToDomainRepresentation(EventSerializer $serializer, StoredAggregateEventStream $storedAggregateEventsStream): AggregateEventStream
{
    $events = [];

    foreach ($storedAggregateEventsStream->storedAggregateEvents as $storedAggregateEvent)
    {
        $events[] = eventToDomainRepresentation($serializer, $storedAggregateEvent);
    }

    /** @var \DateTimeImmutable $createdAt */
    $createdAt = datetimeInstantiator($storedAggregateEventsStream->createdAt);
    /** @var \DateTimeImmutable|null $closedAt */
    $closedAt = datetimeInstantiator($storedAggregateEventsStream->closedAt);

    /** @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $idClass */
    $idClass = $storedAggregateEventsStream->aggregateIdClass;

    /** @var AggregateId $id */
    $id = new $idClass($storedAggregateEventsStream->aggregateId);

    return new AggregateEventStream(
        $id,
        $storedAggregateEventsStream->aggregateClass,
        $events,
        $createdAt,
        $closedAt
    );
}

/**
 * @internal
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
 */
function eventToStoredRepresentation(EventSerializer $serializer, AggregateEvent $aggregateEvent): StoredAggregateEvent
{
    /** @psalm-var class-string $eventClass */
    $eventClass = \get_class($aggregateEvent->event);

    return StoredAggregateEvent::create(
        $aggregateEvent->id,
        $aggregateEvent->playhead,
        $serializer->serialize($aggregateEvent->event),
        $eventClass,
        $aggregateEvent->occuredAt->format('Y-m-d H:i:s.u')
    );
}

/**
 * @internal
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
 */
function eventToDomainRepresentation(EventSerializer $serializer, StoredAggregateEvent $storedAggregateEvent): AggregateEvent
{
    /** @var \DateTimeImmutable $occuredAt */
    $occuredAt = datetimeInstantiator($storedAggregateEvent->occuredAt);

    /** @var \DateTimeImmutable $recordedAt */
    $recordedAt = datetimeInstantiator($storedAggregateEvent->recordedAt);

    return AggregateEvent::restore(
        $storedAggregateEvent->eventId,
        $serializer->unserialize(
            $storedAggregateEvent->eventClass,
            $storedAggregateEvent->eventData
        ),
        $storedAggregateEvent->playheadPosition,
        $occuredAt,
        $recordedAt
    );
}

/**
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 */
function streamToStoredRepresentation(
    EventSerializer $serializer,
    AggregateEventStream $aggregateEvent
): StoredAggregateEventStream {
    $preparedEvents = \array_map(
        static function(AggregateEvent $aggregateEvent) use ($serializer): StoredAggregateEvent
        {
            return eventToStoredRepresentation($serializer, $aggregateEvent);
        },
        $aggregateEvent->events
    );

    /**
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $eventClass
     * @psalm-var array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent> $preparedEvents
     */
    $eventClass = \get_class($aggregateEvent->id);

    return new StoredAggregateEventStream(
        $aggregateEvent->id->toString(),
        $eventClass,
        $aggregateEvent->aggregateClass,
        $preparedEvents,
        (string) datetimeToString($aggregateEvent->createdAt)
    );
}
