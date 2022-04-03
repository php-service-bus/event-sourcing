<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\EventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Contract\AggregateClosed;
use ServiceBus\EventSourcing\Contract\AggregateCreated;
use ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregate;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregateId;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\isUuid;

/**
 *
 */
final class AggregateTest extends TestCase
{
    /**
     * @test
     */
    public function changeClosedStreamState(): void
    {
        $this->expectException(AttemptToChangeClosedStream::class);

        $aggregate = new TestAggregate(TestAggregateId::new());

        invokeReflectionMethod($aggregate, 'close');

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $eventStream */
        $eventStream = invokeReflectionMethod($aggregate, 'makeStream');

        $events = $eventStream->events;

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEvent $aggregateEvent */
        $aggregateEvent = \end($events);

        self::assertInstanceOf(AggregateClosed::class, $aggregateEvent->event);

        /** @var AggregateClosed $aggregateClosedEvent */
        $aggregateClosedEvent = $aggregateEvent->event;

        self::assertTrue(isUuid($aggregateClosedEvent->id));
        self::assertInstanceOf(\DateTimeImmutable::class, $aggregateClosedEvent->datetime);
        self::assertSame(TestAggregate::class, $aggregateClosedEvent->aggregateClass);
        self::assertSame(TestAggregateId::class, $aggregateClosedEvent->idClass);

        $aggregate->firstAction('root');
    }

    /**
     * @test
     */
    public function aggregateCreated(): void
    {
        $aggregate = new TestAggregate(TestAggregateId::new());

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $eventStream */
        $eventStream = invokeReflectionMethod($aggregate, 'makeStream');

        $events = $eventStream->events;

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEvent $aggregateEvent */
        $aggregateEvent = \end($events);

        self::assertInstanceOf(AggregateCreated::class, $aggregateEvent->event);

        /** @var AggregateCreated $aggregateCreatedEvent */
        $aggregateCreatedEvent = $aggregateEvent->event;

        self::assertTrue(isUuid($aggregateCreatedEvent->id));
        self::assertInstanceOf(\DateTimeImmutable::class, $aggregateCreatedEvent->datetime);
        self::assertSame(TestAggregate::class, $aggregateCreatedEvent->aggregateClass);
        self::assertSame(TestAggregateId::class, $aggregateCreatedEvent->idClass);
    }
}
