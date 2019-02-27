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
 * @property-read string      $eventId
 * @property-read int         $playheadPosition
 * @property-read string      $eventData
 * @property-read string      $eventClass
 * @property-read string      $occuredAt
 * @property-read string|null $recordedAt
 */
final class StoredAggregateEvent
{
    /**
     * Event ID.
     *
     * @var string
     */
    public $eventId;

    /**
     * Playhead position.
     *
     * @var int
     */
    public $playheadPosition;

    /**
     * Serialized event data.
     *
     * @var string
     */
    public $eventData;

    /**
     * Event class.
     *
     * @psalm-var class-string
     *
     * @var string
     */
    public $eventClass;

    /**
     * Occured at datetime.
     *
     * @var string
     */
    public $occuredAt;

    /**
     * Recorded at datetime.
     *
     * @var string|null
     */
    public $recordedAt;

    /**
     * @psalm-param class-string $eventClass
     *
     * @param string $eventId
     * @param int    $playheadPosition
     * @param string $eventData
     * @param string $eventClass
     * @param string $occuredAt
     *
     * @return self
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
     *
     * @param string $eventId
     * @param int    $playheadPosition
     * @param string $eventData
     * @param string $eventClass
     * @param string $occuredAt
     * @param string $recordedAt
     *
     * @return self
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
     *
     * @param string      $eventId
     * @param int         $playheadPosition
     * @param string      $eventData
     * @param string      $eventClass
     * @param string      $occuredAt
     * @param string|null $recordedAt
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
