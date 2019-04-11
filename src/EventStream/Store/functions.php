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
 * @param EventSerializer            $serializer
 * @param StoredAggregateEventStream $storedAggregateEventsStream
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
 *
 * @return AggregateEventStream
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

    return AggregateEventStream::create(
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
 * @param EventSerializer $serializer
 * @param AggregateEvent  $aggregateEvent
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
 *
 * @return StoredAggregateEvent
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
        (string) datetimeToString($aggregateEvent->occuredAt)
    );
}

/**
 * @internal
 *
 * @param EventSerializer      $serializer
 * @param StoredAggregateEvent $storedAggregateEvent
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
 *
 * @return AggregateEvent
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
 * @param EventSerializer      $serializer
 * @param AggregateEventStream $aggregateEvent
 *
 * @throws \ServiceBus\Common\Exceptions\DateTimeException
 *
 * @return StoredAggregateEventStream
 */
function streamToStoredRepresentation(EventSerializer $serializer, AggregateEventStream $aggregateEvent): StoredAggregateEventStream
{
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

    return StoredAggregateEventStream::create(
        $aggregateEvent->id->toString(),
        $eventClass,
        $aggregateEvent->aggregateClass,
        $preparedEvents,
        (string) datetimeToString($aggregateEvent->createdAt)
    );
}
