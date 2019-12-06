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
     *
     * @var string
     */
    public $aggregateId;

    /**
     * Aggregate id class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId>
     *
     * @var string
     */
    public $aggregateIdClass;

    /**
     * Aggregate class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     *
     * @var string
     */
    public $aggregateClass;

    /**
     * Stored events data.
     *
     * @psalm-var array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent>
     *
     * @var \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent[]
     */
    public $storedAggregateEvents;

    /**
     * Stream created at datetime.
     *
     * @var string
     */
    public $createdAt;

    /**
     * Stream closed at datetime.
     *
     * @var string|null
     */
    public $closedAt = null;

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
    ) {
        $this->aggregateId           = $aggregateId;
        $this->aggregateIdClass      = $aggregateIdClass;
        $this->aggregateClass        = $aggregateClass;
        $this->storedAggregateEvents = $storedAggregateEvents;
        $this->createdAt             = $createdAt;
        $this->closedAt              = $closedAt;
    }
}
