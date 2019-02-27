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
 * @property-read string                                                                       $aggregateId
 * @property-read string                                                                       $aggregateIdClass
 * @property-read string                                                                       $aggregateClass
 * @property-read array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent> $storedAggregateEvents
 * @property-read string                                                                       $createdAt
 * @property-read string|null                                                                  $closedAt
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
     * @var array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent>
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
    public $closedAt;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\AggregateId> $aggregateIdClass
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     * @psalm-param array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent> $storedAggregateEvents
     *
     * @param string      $aggregateId
     * @param string      $aggregateIdClass
     * @param string      $aggregateClass
     * @param array       $storedAggregateEvents
     * @param string      $createdAt
     * @param string|null $closedAt
     *
     * @return self
     */
    public static function create(
        string $aggregateId,
        string $aggregateIdClass,
        string $aggregateClass,
        array $storedAggregateEvents,
        string $createdAt,
        ?string $closedAt = null
    ): self {
        return new self($aggregateId, $aggregateIdClass, $aggregateClass, $storedAggregateEvents, $createdAt, $closedAt);
    }

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\AggregateId> $aggregateIdClass
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     *
     * @param string                                                                       $aggregateId
     * @param string                                                                       $aggregateIdClass
     * @param string                                                                       $aggregateClass
     * @param array<int, \ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEvent> $storedAggregateEvents
     * @param string                                                                       $createdAt
     * @param string|null                                                                  $closedAt
     */
    private function __construct(
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
