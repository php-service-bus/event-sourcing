<?php

/**
 * Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use function ServiceBus\Common\invokeReflectionMethod;
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
     * @return void
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

        static::assertTrue(Uuid::isValid($aggregateClosedEvent->id));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $aggregateClosedEvent->datetime);
        static::assertEquals(TestAggregate::class, $aggregateClosedEvent->aggregateClass);
        static::assertEquals(TestAggregateId::class, $aggregateClosedEvent->idClass);

        $aggregate->firstAction('root');
    }

    /**
     * @test
     *
     * @return void
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

        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(AggregateCreated::class, $aggregateEvent->event);

        /** @var AggregateCreated $aggregateCreatedEvent */
        $aggregateCreatedEvent = $aggregateEvent->event;

        static::assertTrue(Uuid::isValid($aggregateCreatedEvent->id));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $aggregateCreatedEvent->datetime);
        static::assertEquals(TestAggregate::class, $aggregateCreatedEvent->aggregateClass);
        static::assertEquals(TestAggregateId::class, $aggregateCreatedEvent->idClass);
    }
}
