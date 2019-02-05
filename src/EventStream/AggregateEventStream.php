<?php

/**
 * Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream;

use ServiceBus\Common\Messages\Event;
use ServiceBus\EventSourcing\AggregateId;

/**
 * Event stream
 *
 * @property-read AggregateId                                                      $id
 * @property-read string                                                           $aggregateClass
 * @property-read array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
 * @property-read array<int, \ServiceBus\Common\Messages\Event>                    $originEvents
 * @property-read \DateTimeImmutable                                               $createdAt
 * @property-read \DateTimeImmutable|null                                          $closedAt
 */
final class AggregateEventStream
{
    /**
     * Stream (aggregate) identifier
     *
     * @var AggregateId
     */
    public $id;

    /**
     * Aggregate class
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     * @var string
     */
    public $aggregateClass;

    /**
     * Event collection
     *
     * @var array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent>
     */
    public $events;

    /**
     * Origin event collection
     *
     * @var array<int, \ServiceBus\Common\Messages\Event>
     */
    public $originEvents;

    /**
     * Created at datetime
     *
     * @var \DateTimeImmutable
     */
    public $createdAt;

    /**
     * Closed at datetime
     *
     * @var \DateTimeImmutable|null
     */
    public $closedAt;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     *
     * @param AggregateId                                                      $id
     * @param string                                                           $aggregateClass
     * @param array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     * @param \DateTimeImmutable                                               $createdAt
     * @param \DateTimeImmutable|null                                          $closedAt
     *
     * @return self
     */
    public static function create(
        AggregateId $id,
        string $aggregateClass,
        array $events,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $closedAt
    ): self
    {
        return new self($id, $aggregateClass, $events, $createdAt, $closedAt);
    }

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     *
     * @param AggregateId                                                      $id
     * @param string                                                           $aggregateClass
     * @param array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     * @param \DateTimeImmutable                                               $createdAt
     * @param \DateTimeImmutable|null                                          $closedAt
     */
    private function __construct(
        AggregateId $id,
        string $aggregateClass,
        array $events,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $closedAt
    )
    {
        $this->id             = $id;
        $this->aggregateClass = $aggregateClass;
        $this->events         = self::sortEvents($events);
        $this->originEvents   = self::extractOriginEvents($this->events);
        $this->createdAt      = $createdAt;
        $this->closedAt       = $closedAt;
    }

    /**
     * @param array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     *
     * @return array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent>
     */
    private static function sortEvents(array $events): array
    {
        $result = [];

        foreach($events as $aggregateEvent)
        {
            /** @var \ServiceBus\EventSourcing\EventStream\AggregateEvent $aggregateEvent */

            $result[$aggregateEvent->playhead] = $aggregateEvent;
        }

        \ksort($result);

        return $result;
    }

    /**
     * @param array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent> $events
     *
     * @return array<int, \ServiceBus\Common\Messages\Event>
     */
    private static function extractOriginEvents(array $events): array
    {
        return \array_map(
            static function(AggregateEvent $event): Event
            {
                return $event->event;
            },
            $events
        );
    }
}
