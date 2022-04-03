<?php

declare(strict_types=1);

namespace ServiceBus\EventSourcing\Tests;

use Amp\Loop;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Indexes\IndexKey;
use ServiceBus\EventSourcing\Indexes\IndexValue;
use ServiceBus\EventSourcing\Indexes\Store\IndexStore;
use ServiceBus\EventSourcing\Indexes\Store\SqlIndexStore;
use ServiceBus\EventSourcing\IndexProvider;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function Amp\Promise\wait;

/**
 *
 */
final class IndexProviderTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private static $adapter;

    /**
     * @var IndexStore
     */
    private $indexesStore;

    /**
     * @var IndexProvider
     */
    private $indexProvider;

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

        wait(self::$adapter->execute('DROP TABLE IF EXISTS event_store_stream CASCADE'));
        wait(self::$adapter->execute('DROP TABLE IF EXISTS event_store_stream_events CASCADE'));
        wait(self::$adapter->execute('DROP TABLE IF EXISTS event_store_snapshots CASCADE'));
        wait(self::$adapter->execute('DROP TABLE IF EXISTS event_sourcing_indexes CASCADE'));

        self::$adapter = null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexesStore  = new SqlIndexStore(self::$adapter);
        $this->indexProvider = new IndexProvider($this->indexesStore);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('TRUNCATE TABLE event_sourcing_indexes CASCADE'));

        unset($this->indexesStore, $this->indexProvider);
    }

    /**
     * @test
     */
    public function save(): void
    {
        Loop::run(
            function (): \Generator
            {
                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new IndexValue(__METHOD__);

                /** @var bool $result */
                $result = yield $this->indexProvider->add($index, $value);

                self::assertThat($result, new IsType('bool'));
                self::assertTrue($result);

                /** @var IndexValue|null $storedValue */
                $storedValue = yield $this->indexProvider->get($index);

                self::assertNotNull($storedValue);
                self::assertSame($value->value, $storedValue->value);
            }
        );
    }

    /**
     * @test
     */
    public function saveDuplicate(): void
    {
        Loop::run(
            function (): \Generator
            {
                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new  IndexValue(__METHOD__);

                /** @var bool $result */
                $result = yield $this->indexProvider->add($index, $value);

                self::assertThat($result, new IsType('bool'));
                self::assertTrue($result);

                /** @var bool $result */
                $result = yield $this->indexProvider->add($index, $value);

                self::assertThat($result, new IsType('bool'));
                self::assertFalse($result);
            }
        );
    }

    /**
     * @test
     */
    public function update(): void
    {
        Loop::run(
            function (): \Generator
            {
                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new IndexValue(__METHOD__);

                yield $this->indexProvider->add($index, $value);

                $newValue = new IndexValue('qwerty');

                yield $this->indexProvider->update($index, $newValue);

                /** @var IndexValue|null $storedValue */
                $storedValue = yield $this->indexProvider->get($index);

                self::assertNotNull($storedValue);
                self::assertSame($newValue->value, $storedValue->value);
            }
        );
    }

    /**
     * @test
     */
    public function remove(): void
    {
        Loop::run(
            function (): \Generator
            {
                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new IndexValue(__METHOD__);

                yield $this->indexProvider->add($index, $value);
                yield $this->indexProvider->remove($index);

                self::assertNull(yield $this->indexProvider->get($index));
            }
        );
    }

    /**
     * @test
     */
    public function has(): void
    {
        Loop::run(
            function (): \Generator
            {
                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new IndexValue(__METHOD__);

                self::assertFalse(yield $this->indexProvider->has($index));

                yield $this->indexProvider->add($index, $value);

                self::assertTrue(yield $this->indexProvider->has($index));
            }
        );
    }
}
