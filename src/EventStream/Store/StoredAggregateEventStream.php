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

/**
 * Aggregate event stream data.
 *
 * @psalm-readonly
 */
final class StoredAggregateEventStream
{
    /**
     * Aggregate id.
     */
    public string $aggregateId;

    /**
     * Aggregate id class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId>
     */
    public string $aggregateIdClass;

    /**
     * Aggregate class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     */
    public string $aggregateClass;

    /**
     * Stored events data.
     *
     * @psalm-var array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent>
     *
     * @var \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent[]
     */
    public array $storedAggregateEvents;

    /**
     * Stream created at datetime.
     */
    public string $createdAt;

    /**
     * Stream closed at datetime.
     */
    public ?string $closedAt = null;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\AggregateId> $aggregateIdClass
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     * @psalm-param array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent> $storedAggregateEvents
     *
     * @param \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent[] $storedAggregateEvents
     */
    public function __construct(
        string $aggregateId,
        string $aggregateIdClass,
        string $aggregateClass,
        array $storedAggregateEvents,
        string $createdAt,
        ?string $closedAt = null
    )
    {
        $this->aggregateId           = $aggregateId;
        $this->aggregateIdClass      = $aggregateIdClass;
        $this->aggregateClass        = $aggregateClass;
        $this->storedAggregateEvents = $storedAggregateEvents;
        $this->createdAt             = $createdAt;
        $this->closedAt              = $closedAt;
    }
}
