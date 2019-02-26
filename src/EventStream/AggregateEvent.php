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

/**
 * Applied to aggregate event
 *
 * @property-read string                  $id
 * @property-read int                     $playhead
 * @property-read object                  $event
 * @property-read \DateTimeImmutable      $occuredAt
 * @property-read \DateTimeImmutable|null $recordedAt
 */
final class AggregateEvent
{
    /**
     * Event id
     *
     * @var string
     */
    public $id;

    /**
     * Playhead position
     *
     * @var int
     */
    public $playhead;

    /**
     * Received event
     *
     * @var object
     */
    public $event;

    /**
     * Occurred datetime
     *
     * @var \DateTimeImmutable
     */
    public $occuredAt;

    /**
     * Recorded datetime
     *
     * @var \DateTimeImmutable|null
     */
    public $recordedAt;

    /**
     * @param string             $id
     * @param object             $event
     * @param int                $playhead
     * @param \DateTimeImmutable $occuredAt
     *
     * @return self
     */
    public static function create(string $id, object $event, int $playhead, \DateTimeImmutable $occuredAt): self
    {
        return new self($id, $event, $playhead, $occuredAt, null);
    }

    /**
     * @param string             $id
     * @param object             $event
     * @param int                $playhead
     * @param \DateTimeImmutable $occuredAt
     * @param \DateTimeImmutable $recordedAt
     *
     * @return self
     */
    public static function restore(string $id, object $event, int $playhead, \DateTimeImmutable $occuredAt, \DateTimeImmutable $recordedAt): self
    {
        return new self($id, $event, $playhead, $occuredAt, $recordedAt);
    }

    /**
     * @param string                  $id
     * @param object                  $event
     * @param int                     $playhead
     * @param \DateTimeImmutable      $occuredAt
     * @param \DateTimeImmutable|null $recordedAt
     */
    private function __construct(
        string $id,
        object $event,
        int $playhead,
        \DateTimeImmutable $occuredAt,
        ?\DateTimeImmutable $recordedAt = null
    )
    {
        $this->id         = $id;
        $this->event      = $event;
        $this->playhead   = $playhead;
        $this->occuredAt  = $occuredAt;
        $this->recordedAt = $recordedAt;
    }
}
