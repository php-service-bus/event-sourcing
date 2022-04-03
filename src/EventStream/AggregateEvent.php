<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

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
     * @psalm-var non-empty-string
     * @psalm-readonly
     *
     * @var string
     */
    public $id;

    /**
     * Playhead position.
     *
     * @psalm-var positive-int
     * @psalm-readonly
     *
     * @var int
     */
    public $playhead;

    /**
     * Received event.
     *
     * @psalm-readonly
     *
     * @var object
     */
    public $event;

    /**
     * Occurred datetime.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $occurredAt;

    /**
     * Recorded datetime.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable|null
     */
    public $recordedAt;

    /**
     * @psalm-param non-empty-string $id
     * @psalm-param positive-int $playhead
     */
    public static function create(string $id, object $event, int $playhead, \DateTimeImmutable $occurredAt): self
    {
        return new self(
            id: $id,
            event: $event,
            playhead: $playhead,
            occurredAt: $occurredAt,
            recordedAt: null
        );
    }

    /**
     * @psalm-param non-empty-string $id
     * @psalm-param positive-int $playhead
     */
    public static function restore(
        string $id,
        object $event,
        int $playhead,
        \DateTimeImmutable $occuredAt,
        \DateTimeImmutable $recordedAt
    ): self {
        return new self(
            id: $id,
            event: $event,
            playhead: $playhead,
            occurredAt: $occuredAt,
            recordedAt: $recordedAt
        );
    }

    /**
     * @psalm-param non-empty-string $id
     * @psalm-param positive-int $playhead
     */
    private function __construct(
        string $id,
        object $event,
        int $playhead,
        \DateTimeImmutable $occurredAt,
        ?\DateTimeImmutable $recordedAt = null
    ) {
        $this->id         = $id;
        $this->event      = $event;
        $this->playhead   = $playhead;
        $this->occurredAt = $occurredAt;
        $this->recordedAt = $recordedAt;
    }
}
