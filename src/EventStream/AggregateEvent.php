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
 * @psalm-readonly
 */
final class AggregateEvent
{
    /**
     * Event id.
     */
    public string $id;

    /**
     * Playhead position.
     */
    public int $playhead;

    /**
     * Received event.
     */
    public object $event;

    /**
     * Occurred datetime.
     */
    public \DateTimeImmutable$occuredAt;

    /**
     * Recorded datetime.
     */
    public ?\DateTimeImmutable $recordedAt = null;

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
    ): self
    {
        return new self($id, $event, $playhead, $occuredAt, $recordedAt);
    }

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
