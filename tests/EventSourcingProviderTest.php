<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests;

use ServiceBus\MessageSerializer\Symfony\SymfonySerializer;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\fetchOne;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\EventSourcingProvider;
use ServiceBus\EventSourcing\EventStream\EventStreamRepository;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\SqlEventStreamStore;
use ServiceBus\EventSourcing\Exceptions\DuplicateAggregate;
use ServiceBus\EventSourcing\Exceptions\RevertAggregateVersionFailed;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;
use ServiceBus\EventSourcing\Snapshots\Store\SnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Store\SqlSnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotVersionTrigger;
use ServiceBus\EventSourcing\Tests\stubs\Context;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregate;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregateId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;

/**
 *
 */
final class EventSourcingProviderTest extends TestCase
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
     * @var EventSourcingProvider
     */
    private $eventSourcingProvider;

    /**
     * @var TestHandler
     */
    public $testLogHandler;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$adapter = new AmpPostgreSQLAdapter(
            new StorageConfiguration((string) \getenv('TEST_POSTGRES_DSN'))
        );

        $schemas = [
            __DIR__ . '/../src/Indexes/Store/schema/event_sourcing_indexes.sql',
            __DIR__ . '/../src/EventStream/Store/schema/extensions.sql',
            __DIR__ . '/../src/EventStream/Store/schema/event_store_stream.sql',
            __DIR__ . '/../src/EventStream/Store/schema/event_store_stream_events.sql',
            __DIR__ . '/../src/EventStream/Store/schema/indexes.sql',
            __DIR__ . '/../src/Snapshots/Store/schema/event_store_snapshots.sql',
            __DIR__ . '/../src/Snapshots/Store/schema/indexes.sql',
        ];

        foreach ($schemas as $schema)
        {
            $queries = \array_filter(
                \explode(';', \file_get_contents($schema)),
                static function (string $element): ?string
                {
                    $element = \trim($element);

                    return '' !== $element ? $element : null;
                }
            );

            foreach ($queries as $query)
            {
                wait(self::$adapter->execute($query));
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        wait(self::$adapter->execute('DROP TABLE event_store_stream CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_store_stream_events CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_store_snapshots CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_sourcing_indexes CASCADE'));

        self::$adapter = null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testLogHandler        = new TestHandler();
        $this->eventStore            = new SqlEventStreamStore(self::$adapter);
        $this->snapshotStore         = new SqlSnapshotStore(self::$adapter);
        $this->snapshotter           = new Snapshotter($this->snapshotStore, new SnapshotVersionTrigger(2));
        $this->eventStreamRepository = new EventStreamRepository(
            $this->eventStore,
            $this->snapshotter,
            new SymfonySerializer(),
            new Logger('tests', [$this->testLogHandler])
        );

        $this->eventSourcingProvider = new EventSourcingProvider($this->eventStreamRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('TRUNCATE TABLE event_store_stream CASCADE'));
        wait(self::$adapter->execute('TRUNCATE TABLE event_store_stream_events CASCADE'));
        wait(self::$adapter->execute('TRUNCATE TABLE event_store_snapshots CASCADE'));

        unset($this->eventStore, $this->snapshotStore, $this->snapshotter, $this->eventStreamRepository, $this->eventSourcingProvider, $this->testLogHandler);
    }

    /**
     * @test
     */
    public function flow(): \Generator
    {
        $context   = new Context();
        $aggregate = new TestAggregate(TestAggregateId::new());

        yield $this->eventSourcingProvider->save($aggregate, $context);

        self::assertCount(1, $context->messages);

        /** @var Aggregate $loadedAggregate */
        $loadedAggregate = yield $this->eventSourcingProvider->load($aggregate->id());

        self::assertNotNull($loadedAggregate);
    }

    /**
     * @test
     */
    public function saveDuplicate(): \Generator
    {
        $this->expectException(DuplicateAggregate::class);

        $id = TestAggregateId::new();

        $context = new Context();

        yield $this->eventSourcingProvider->save(new TestAggregate($id), $context);
        yield $this->eventSourcingProvider->save(new TestAggregate($id), $context);
    }

    /**
     * @test
     */
    public function successHardDeleteRevert(): \Generator
    {
        $context   = new Context();
        $aggregate = new TestAggregate(TestAggregateId::new());

        yield $this->eventSourcingProvider->save($aggregate, $context);

        foreach (\range(1, 6) as $item)
        {
            $aggregate->firstAction($item + 1 . ' event');
        }

        /** 7 aggregate version */
        yield $this->eventSourcingProvider->save($aggregate, $context);

        /** 7 aggregate version */
        self::assertSame(7, $aggregate->version());
        self::assertSame('7 event', $aggregate->firstValue());

        /** @var TestAggregate $aggregate */
        $aggregate = yield  $this->eventSourcingProvider->revert(
            $aggregate,
            5,
            EventStreamRepository::REVERT_MODE_DELETE
        );

        /** 7 aggregate version */
        self::assertSame(5, $aggregate->version());
        self::assertSame('5 event', $aggregate->firstValue());

        $eventsCount = yield  fetchOne(
            yield self::$adapter->execute('SELECT COUNT(id) as cnt FROM event_store_stream_events')
        );

        self::assertSame(5, $eventsCount['cnt']);
    }

    /**
     * @test
     */
    public function revertUnknownStream(): \Generator
    {
        $this->expectException(RevertAggregateVersionFailed::class);

        yield $this->eventSourcingProvider->revert(
            new TestAggregate(TestAggregateId::new()),
            20
        );
    }

    /**
     * @test
     */
    public function revertWithVersionConflict(): \Generator
    {
        $this->expectException(RevertAggregateVersionFailed::class);

        $context   = new Context();
        $aggregate = new TestAggregate(TestAggregateId::new());

        $aggregate->firstAction('qwerty');
        $aggregate->firstAction('root');
        $aggregate->firstAction('qwertyRoot');

        yield $this->eventSourcingProvider->save($aggregate, $context);

        /** @var TestAggregate $aggregate */
        $aggregate = yield $this->eventSourcingProvider->revert($aggregate, 2);

        $aggregate->firstAction('abube');

        yield $this->eventSourcingProvider->save($aggregate, $context);
        yield $this->eventSourcingProvider->revert($aggregate, 3);
    }
}
