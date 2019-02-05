<?php

/**
 * Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\EventStream;

use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use function ServiceBus\Common\invokeReflectionMethod;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\Contract\AggregateCreated;
use ServiceBus\EventSourcing\EventStream\EventStreamRepository;
use ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamDoesNotExist;
use ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamIntegrityCheckFailed;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\SqlEventStreamStore;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;
use ServiceBus\EventSourcing\Snapshots\Store\SnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Store\SqlSnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotVersionTrigger;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregate;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregateId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function ServiceBus\Storage\Sql\fetchOne;

/**
 *
 */
final class EventStreamRepositoryTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private static $adapter;

    /**
     * @var EventStreamStore
     */
    private $eventStore;

    /**
     * @var SnapshotStore
     */
    private $snapshotStore;

    /**
     * @var Snapshotter
     */
    private $snapshotter;

    /**
     * @var EventStreamRepository
     */
    private $eventStreamRepository;

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$adapter = new AmpPostgreSQLAdapter(
            new StorageConfiguration((string) \getenv('TEST_POSTGRES_DSN'))
        );

        $queries = \array_map('trim', \array_merge([
            \file_get_contents(__DIR__ . '/../../src/EventStream/Store/schema/event_store_stream.sql'),
            \file_get_contents(__DIR__ . '/../../src/EventStream/Store/schema/event_store_stream_events.sql'),
            \file_get_contents(__DIR__ . '/../../src/EventStream/Store/schema/extensions.sql'),
            \file_get_contents(__DIR__ . '/../../src/Snapshots/Store/schema/event_store_snapshots.sql')
        ],
            \file(__DIR__ . '/../../src/EventStream/Store/schema/indexes.sql'),
            \file(__DIR__ . '/../../src/Snapshots/Store/schema/indexes.sql')

        ));

        foreach($queries as $query)
        {
            if('' !== $query)
            {
                wait(self::$adapter->execute($query));
            }
        }
    }

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        wait(self::$adapter->execute('DROP TABLE event_store_stream CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_store_stream_events CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_store_snapshots CASCADE'));

        self::$adapter = null;
    }

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStore            = new SqlEventStreamStore(self::$adapter);
        $this->snapshotStore         = new SqlSnapshotStore(self::$adapter);
        $this->snapshotter           = new Snapshotter($this->snapshotStore, new SnapshotVersionTrigger(1));
        $this->eventStreamRepository = new EventStreamRepository($this->eventStore, $this->snapshotter);
    }

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('TRUNCATE TABLE event_store_stream CASCADE'));
        wait(self::$adapter->execute('TRUNCATE TABLE event_store_stream_events CASCADE'));
        wait(self::$adapter->execute('TRUNCATE TABLE event_store_snapshots CASCADE'));

        unset($this->eventStore, $this->snapshotStore, $this->snapshotter, $this->eventStreamRepository);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function flow(): void
    {
        $aggregate = new TestAggregate(TestAggregateId::new());

        $events = wait($this->eventStreamRepository->save($aggregate));

        static::assertCount(1, $events);

        /** @var AggregateCreated $event */
        $event = \end($events);

        static::assertInstanceOf(AggregateCreated::class, $event);

        $loadedAggregate = wait($this->eventStreamRepository->load($aggregate->id()));

        static::assertNotNull($loadedAggregate);
        static::assertInstanceOf(Aggregate::class, $loadedAggregate);

        /** @var Aggregate $loadedAggregate */

        static::assertEquals(1, $loadedAggregate->version());

        /** @var \ServiceBus\EventSourcing\EventStream\AggregateEventStream $stream */
        $stream = invokeReflectionMethod($loadedAggregate, 'makeStream');

        static::assertCount(0, $stream->events);

        $events = wait($this->eventStreamRepository->update($loadedAggregate));

        static::assertCount(0, $events);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function loadWithSnapshot(): void
    {
        $aggregate = new TestAggregate(TestAggregateId::new());

        $events = wait($this->eventStreamRepository->save($aggregate));

        static::assertCount(1, $events);

        /** first action */
        $aggregate->firstAction('qwerty');

        $events = wait($this->eventStreamRepository->update($aggregate));

        static::assertCount(1, $events);

        /** second action  */
        $aggregate->secondAction('root');

        $events = wait($this->eventStreamRepository->update($aggregate));

        static::assertCount(1, $events);

        /** assert values */
        static::assertNotNull($aggregate->firstValue());
        static::assertNotNull($aggregate->secondValue());

        static::assertEquals('qwerty', $aggregate->firstValue());
        static::assertEquals('root', $aggregate->secondValue());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function saveDuplicateAggregate(): void
    {
        $this->expectException(UniqueConstraintViolationCheckFailed::class);

        $id = TestAggregateId::new();

        wait($this->eventStreamRepository->save(new TestAggregate($id)));
        wait($this->eventStreamRepository->save(new TestAggregate($id)));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function loadWithoutSnapshot(): void
    {
        $repository = new EventStreamRepository(
            $this->eventStore,
            new Snapshotter(
                $this->snapshotStore,
                new SnapshotVersionTrigger(100500)
            )
        );

        $id = TestAggregateId::new();

        $aggregate = new TestAggregate($id);

        wait($repository->save($aggregate));

        wait($this->snapshotStore->remove($id));

        /** @var \ServiceBus\EventSourcing\Aggregate|null $aggregate */
        $aggregate = wait($repository->load($id));

        static::assertNotNull($aggregate);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function successSoftDeleteRevert(): void
    {
        $aggregate = new TestAggregate(TestAggregateId::new());

        wait($this->eventStreamRepository->save($aggregate));

        foreach(\range(1, 6) as $item)
        {
            $aggregate->firstAction($item + 1 . ' event');
        }

        /** 7 aggregate version */
        wait($this->eventStreamRepository->update($aggregate));

        /** 7 aggregate version */
        static::assertEquals(7, $aggregate->version());
        static::assertEquals('7 event', $aggregate->firstValue());

        /** @var TestAggregate $aggregate */
        $aggregate = wait(
            $this->eventStreamRepository->revert(
                $aggregate, 5, EventStreamRepository::REVERT_MODE_SOFT_DELETE
            )
        );

        static::assertEquals(5, $aggregate->version());
        static::assertEquals('5 event', $aggregate->firstValue());

        foreach(\range(1, 6) as $item)
        {
            $aggregate->firstAction($item + 5 . ' new event');
        }

        /** 7 aggregate version */
        wait($this->eventStreamRepository->update($aggregate));

        static::assertEquals(11, $aggregate->version());
        static::assertEquals('11 new event', $aggregate->firstValue());
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function successHardDeleteRevert(): void
    {
        $aggregate = new TestAggregate(TestAggregateId::new());

        wait($this->eventStreamRepository->save($aggregate));

        foreach(\range(1, 6) as $item)
        {
            $aggregate->firstAction($item + 1 . ' event');
        }

        /** 7 aggregate version */
        wait($this->eventStreamRepository->update($aggregate));

        /** 7 aggregate version */
        static::assertEquals(7, $aggregate->version());
        static::assertEquals('7 event', $aggregate->firstValue());

        /** @var TestAggregate $aggregate */
        $aggregate = wait(
            $this->eventStreamRepository->revert(
                $aggregate, 5, EventStreamRepository::REVERT_MODE_DELETE
            )
        );

        /** 7 aggregate version */
        static::assertEquals(5, $aggregate->version());
        static::assertEquals('5 event', $aggregate->firstValue());

        $eventsCount = wait(
            fetchOne(
                wait(self::$adapter->execute('SELECT COUNT(id) as cnt FROM event_store_stream_events'))
            )
        );

        static::assertEquals(5, $eventsCount['cnt']);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function revertUnknownStream(): void
    {
        $this->expectException(EventStreamDoesNotExist::class);

        wait($this->eventStreamRepository->revert(new TestAggregate(TestAggregateId::new()), 20));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function revertWithVersionConflict(): void
    {
        $this->expectException(EventStreamIntegrityCheckFailed::class);

        $aggregate = new TestAggregate(TestAggregateId::new());

        $aggregate->firstAction('qwerty');
        $aggregate->firstAction('root');
        $aggregate->firstAction('qwertyRoot');

        wait($this->eventStreamRepository->save($aggregate));

        /** @var TestAggregate $aggregate */
        $aggregate = wait(
            $this->eventStreamRepository->revert(
                $aggregate, 2, EventStreamRepository::REVERT_MODE_SOFT_DELETE)
        );

        $aggregate->firstAction('abube');

        wait($this->eventStreamRepository->update($aggregate));
        wait(
            $this->eventStreamRepository->revert(
                $aggregate, 3, EventStreamRepository::REVERT_MODE_SOFT_DELETE
            )
        );
    }
}
