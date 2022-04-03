<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\EventStream\Store;

/**
 * Aggregate event data.
 *
 * @psalm-immutable
 */
final class StoredAggregateEvent
{
    /**
     * Event ID.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $eventId;

    /**
     * Playhead position.
     *
     * @psalm-readonly
     * @psalm-var positive-int
     *
     * @var int
     */
    public $playheadPosition;

    /**
     * Serialized event data.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $eventData;

    /**
     * Event class.
     *
     * @psalm-readonly
     * @psalm-var class-string
     *
     * @var string
     */
    public $eventClass;

    /**
     * Occured at datetime.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $occurredAt;

    /**
     * Recorded at datetime.
     *
     * @psalm-readonly
     *
     * @var string|null
     */
    public $recordedAt;

    /**
     * @psalm-param non-empty-string $eventId
     * @psalm-param positive-int $playheadPosition
     * @psalm-param non-empty-string $eventData
     * @psalm-param class-string $eventClass
     */
    public static function create(
        string $eventId,
        int $playheadPosition,
        string $eventData,
        string $eventClass,
        string $occurredAt
    ): self {
        return new self(
            eventId: $eventId,
            playheadPosition: $playheadPosition,
            eventData: $eventData,
            eventClass: $eventClass,
            occurredAt: $occurredAt
        );
    }

    /**
     * @psalm-param non-empty-string $eventId
     * @psalm-param positive-int $playheadPosition
     * @psalm-param non-empty-string $eventData
     * @psalm-param class-string $eventClass
     */
    public static function restore(
        string $eventId,
        int $playheadPosition,
        string $eventData,
        string $eventClass,
        string $occurredAt,
        string $recordedAt
    ): self {
        return new self(
            eventId: $eventId,
            playheadPosition: $playheadPosition,
            eventData: $eventData,
            eventClass: $eventClass,
            occurredAt: $occurredAt,
            recordedAt: $recordedAt
        );
    }

    /**
     * @psalm-param non-empty-string $eventId
     * @psalm-param positive-int $playheadPosition
     * @psalm-param non-empty-string $eventData
     * @psalm-param class-string $eventClass
     */
    private function __construct(
        string $eventId,
        int $playheadPosition,
        string $eventData,
        string $eventClass,
        string $occurredAt,
        ?string $recordedAt = null
    ) {
        $this->eventId          = $eventId;
        $this->playheadPosition = $playheadPosition;
        $this->eventData        = $eventData;
        $this->eventClass       = $eventClass;
        $this->occurredAt        = $occurredAt;
        $this->recordedAt       = $recordedAt;
    }
}
