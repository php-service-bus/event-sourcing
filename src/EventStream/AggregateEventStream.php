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

use ServiceBus\EventSourcing\AggregateId;

/**
 * Event stream.
 *
 * @psalm-immutable
 */
final class AggregateEventStream
{
    /**
     * Stream (aggregate) identifier.
     *
     * @psalm-readonly
     *
     * @var AggregateId
     */
    public $id;

    /**
     * Aggregate class.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     *
     * @var string
     */
    public $aggregateClass;

    /**
     * Event collection.
     *
     * @psalm-readonly
     * @psalm-var array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent>
     *
     * @var \ServiceBus\EventSourcing\EventStream\AggregateEvent[]
     */
    public $events;

    /**
     * Origin event collection.
     *
     * @psalm-readonly
     * @psalm-var array<int, object>
     *
     * @var list<object>
     */
    public $originEvents;

    /**
     * Created at datetime.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $createdAt;

    /**
     * Closed at datetime.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable|null
     */
    public $closedAt;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate>                $aggregateClass
     * @psalm-param array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     *
     * @param \ServiceBus\EventSourcing\EventStream\AggregateEvent[]                 $events
     */
    public function __construct(
        AggregateId         $id,
        string              $aggregateClass,
        array               $events,
        \DateTimeImmutable  $createdAt,
        ?\DateTimeImmutable $closedAt
    ) {
        $this->id             = $id;
        $this->aggregateClass = $aggregateClass;
        $this->events         = self::sortEvents($events);
        $this->originEvents   = self::extractOriginEvents($this->events);
        $this->createdAt      = $createdAt;
        $this->closedAt       = $closedAt;
    }

    /**
     * @psalm-param array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     *
     * @psalm-return array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent>
     */
    private static function sortEvents(array $events): array
    {
        $result = [];

        foreach ($events as $aggregateEvent)
        {
            /** @var \ServiceBus\EventSourcing\EventStream\AggregateEvent $aggregateEvent */
            $result[$aggregateEvent->playhead] = $aggregateEvent;
        }

        \ksort($result);

        return $result;
    }

    /**
     * @psalm-param  array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     * @psalm-return array<int, object>
     *
     * @param \ServiceBus\EventSourcing\EventStream\AggregateEvent[]                  $events
     *
     * @return object[]
     */
    private static function extractOriginEvents(array $events): array
    {
        return \array_map(
            static function (AggregateEvent $event): object
            {
                return $event->event;
            },
            $events
        );
    }
}
