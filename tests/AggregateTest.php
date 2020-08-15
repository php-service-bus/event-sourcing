<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests;

use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\isUuid;
use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Contract\AggregateClosed;
use ServiceBus\EventSourcing\Contract\AggregateCreated;
use ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregate;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregateId;

/**
 *
 */
final class AggregateTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
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

        static::assertInstanceOf(AggregateClosed::class, $aggregateEvent->event);

        /** @var AggregateClosed $aggregateClosedEvent */
        $aggregateClosedEvent = $aggregateEvent->event;

        static::assertTrue(isUuid($aggregateClosedEvent->id));
        static::assertInstanceOf(\DateTimeImmutable::class, $aggregateClosedEvent->datetime);
        static::assertSame(TestAggregate::class, $aggregateClosedEvent->aggregateClass);
        static::assertSame(TestAggregateId::class, $aggregateClosedEvent->idClass);

        $aggregate->firstAction('root');
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function aggregateCreated(): void
    {
        $aggregate = new TestAggregate(TestAggregateId::new());

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $eventStream */
        $eventStream = invokeReflectionMethod($aggregate, 'makeStream');

        $events = $eventStream->events;

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEvent $aggregateEvent */
        $aggregateEvent = \end($events);

        static::assertInstanceOf(AggregateCreated::class, $aggregateEvent->event);

        /** @var AggregateCreated $aggregateCreatedEvent */
        $aggregateCreatedEvent = $aggregateEvent->event;

        static::assertTrue(isUuid($aggregateCreatedEvent->id));
        static::assertInstanceOf(\DateTimeImmutable::class, $aggregateCreatedEvent->datetime);
        static::assertSame(TestAggregate::class, $aggregateCreatedEvent->aggregateClass);
        static::assertSame(TestAggregateId::class, $aggregateCreatedEvent->idClass);
    }
}
