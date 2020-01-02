<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream;

/**
 * Applied to aggregate event.
 *
 * @psalm-immutable
 */
final class AggregateEvent
{
    /**
     * Event id.
     *
     * @var string
     */
    public $id;

    /**
     * Playhead position.
     *
     * @var int
     */
    public $playhead;

    /**
     * Received event.
     *
     * @var object
     */
    public $event;

    /**
     * Occurred datetime.
     *
     * @var \DateTimeImmutable
     */
    public $occuredAt;

    /**
     * Recorded datetime.
     *
     * @var \DateTimeImmutable|null
     */
    public $recordedAt = null;

    public static function create(string $id, object $event, int $playhead, \DateTimeImmutable $occuredAt): self
    {
        return new self($id, $event, $playhead, $occuredAt, null);
    }

    public static function restore(
        string $id,
        object $event,
        int $playhead,
        \DateTimeImmutable $occuredAt,
        \DateTimeImmutable $recordedAt
    ): self {
        return new self($id, $event, $playhead, $occuredAt, $recordedAt);
    }

    private function __construct(
        string $id,
        object $event,
        int $playhead,
        \DateTimeImmutable $occuredAt,
        ?\DateTimeImmutable $recordedAt = null
    ) {
        $this->id         = $id;
        $this->event      = $event;
        $this->playhead   = $playhead;
        $this->occuredAt  = $occuredAt;
        $this->recordedAt = $recordedAt;
    }
}
