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
 * Aggregate event data.
 *
 * @psalm-readonly
 */
final class StoredAggregateEvent
{
    /**
     * Event ID.
     */
    public string $eventId;

    /**
     * Playhead position.
     */
    public int $playheadPosition;

    /**
     * Serialized event data.
     */
    public string $eventData;

    /**
     * Event class.
     *
     * @psalm-var class-string
     */
    public string $eventClass;

    /**
     * Occured at datetime.
     */
    public string $occuredAt;

    /**
     * Recorded at datetime.
     */
    public ?string $recordedAt = null;

    /**
     * @psalm-param class-string $eventClass
     */
    public static function create(
        string $eventId,
        int $playheadPosition,
        string $eventData,
        string $eventClass,
        string $occuredAt
    ): self {
        return new self($eventId, $playheadPosition, $eventData, $eventClass, $occuredAt);
    }

    /**
     * @psalm-param class-string $eventClass
     */
    public static function restore(
        string $eventId,
        int $playheadPosition,
        string $eventData,
        string $eventClass,
        string $occuredAt,
        string $recordedAt
    ): self {
        return new self($eventId, $playheadPosition, $eventData, $eventClass, $occuredAt, $recordedAt);
    }

    /**
     * @psalm-param class-string $eventClass
     */
    private function __construct(
        string $eventId,
        int $playheadPosition,
        string $eventData,
        string $eventClass,
        string $occuredAt,
        ?string $recordedAt = null
    ) {
        $this->eventId          = $eventId;
        $this->playheadPosition = $playheadPosition;
        $this->eventData        = $eventData;
        $this->eventClass       = $eventClass;
        $this->occuredAt        = $occuredAt;
        $this->recordedAt       = $recordedAt;
    }
}
