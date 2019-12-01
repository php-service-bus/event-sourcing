<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing;

use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\uuid;
use ServiceBus\EventSourcing\Contract\AggregateClosed;
use ServiceBus\EventSourcing\Contract\AggregateCreated;
use ServiceBus\EventSourcing\EventStream\AggregateEvent;
use ServiceBus\EventSourcing\EventStream\AggregateEventStream;
use ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream;

/**
 * Aggregate base class.
 */
abstract class Aggregate
{
    public const   START_PLAYHEAD_INDEX = 0;

    private const  EVENT_APPLY_PREFIX = 'on';

    private const INTERNAL_EVENTS = [
        AggregateCreated::class,
        AggregateClosed::class,
    ];

    private const INCREASE_VERSION_STEP = 1;

    /**
     * Aggregate identifier.
     */
    private AggregateId $id;

    /**
     * Current version.
     */
    private int $version = self::START_PLAYHEAD_INDEX;

    /**
     * List of applied aggregate events.
     *
     * @psalm-var array<int, \ServiceBus\EventSourcing\EventStream\AggregateEvent>
     *
     * @var \ServiceBus\EventSourcing\EventStream\AggregateEvent[]
     */
    private array $events;

    /**
     * Created at datetime.
     */
    private \DateTimeImmutable $createdAt;

    /**
     * Closed at datetime.
     */
    private ?\DateTimeImmutable $closedAt = null;


    final public function __construct(AggregateId $id)
    {
        $this->id = $id;

        $this->clearEvents();

        $this->raise(
            new AggregateCreated($id, \get_class($this))
        );
    }

    /**
     * Receive id.
     */
    final public function id(): AggregateId
    {
        return $this->id;
    }

    /**
     * Receive created at datetime.
     */
    final public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Raise (apply event).
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream
     */
    final protected function raise(object $event): void
    {
        if(null !== $this->closedAt)
        {
            throw new AttemptToChangeClosedStream($this->id);
        }

        $specifiedEvent = $event;

        $this->attachEvent($specifiedEvent);
        $this->applyEvent($specifiedEvent);
    }

    /**
     * Receive aggregate version.
     */
    final public function version(): int
    {
        return $this->version;
    }

    /**
     * Close aggregate (make it read-only).
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream
     */
    final protected function close(): void
    {
        /** @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass */
        $aggregateClass = \get_class($this);

        $this->raise(
            new AggregateClosed($this->id, $aggregateClass)
        );
    }

    /**
     * On aggregate closed.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function onAggregateClosed(AggregateClosed $event): void
    {
        $this->closedAt = $event->datetime;
    }

    /**
     * On aggregate created.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function onAggregateCreated(AggregateCreated $event): void
    {
        $this->createdAt = $event->datetime;
    }

    /**
     * Receive uncommitted events as stream.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @see          EventSourcingProvider::save()
     */
    private function makeStream(): AggregateEventStream
    {
        $events = $this->events;

        /** @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass */
        $aggregateClass = \get_class($this);

        $this->clearEvents();

        return new AggregateEventStream(
            $this->id,
            $aggregateClass,
            $events,
            $this->createdAt,
            $this->closedAt
        );
    }

    /**
     * Restore from event stream.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @see          EventSourcingProvider::load()
     */
    private function appendStream(AggregateEventStream $aggregateEventsStream): void
    {
        $this->clearEvents();

        $this->id = $aggregateEventsStream->id;

        /** @var AggregateEvent $aggregateEvent */
        foreach($aggregateEventsStream->events as $aggregateEvent)
        {
            $this->applyEvent($aggregateEvent->event);

            $this->increaseVersion(self::INCREASE_VERSION_STEP);
        }
    }

    /**
     * Attach event to stream
     */
    private function attachEvent(object $event): void
    {
        $this->increaseVersion(self::INCREASE_VERSION_STEP);

        /** @var \DateTimeImmutable $currentDate */
        $currentDate = datetimeInstantiator('NOW');

        $this->events[] = AggregateEvent::create(uuid(), $event, $this->version, $currentDate);
    }

    /**
     * Apply event.
     */
    private function applyEvent(object $event): void
    {
        $eventListenerMethodName = self::createListenerName($event);

        true === self::isInternalEvent($event)
            ? $this->processInternalEvent($eventListenerMethodName, $event)
            : $this->processChildEvent($eventListenerMethodName, $event);
    }

    /**
     * Is internal event (for current class).
     */
    private static function isInternalEvent(object $event): bool
    {
        return true === \in_array(\get_class($event), self::INTERNAL_EVENTS, true);
    }

    private function processInternalEvent(string $listenerName, object $event): void
    {
        $this->{$listenerName}($event);
    }

    private function processChildEvent(string $listenerName, object $event): void
    {
        /**
         * Call child class method.
         *
         * @param object $event
         *
         * @return void
         */
        $closure = function(object $event) use ($listenerName): void
        {
            if(true === \method_exists($this, $listenerName))
            {
                $this->{$listenerName}($event);
            }
        };

        $closure->call($this, $event);
    }

    /**
     * Create event listener name.
     */
    private static function createListenerName(object $event): string
    {
        $eventListenerMethodNameParts = \explode('\\', \get_class($event));

        /** @var string $latestPart */
        $latestPart = \end($eventListenerMethodNameParts);

        return \sprintf(
            '%s%s',
            self::EVENT_APPLY_PREFIX,
            $latestPart
        );
    }

    /**
     * Increase aggregate version.
     */
    private function increaseVersion(int $step): void
    {
        $this->version += $step;
    }

    /**
     * Clear all aggregate events.
     */
    private function clearEvents(): void
    {
        $this->events = [];
    }
}
