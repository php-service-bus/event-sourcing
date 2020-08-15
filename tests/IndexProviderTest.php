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

use function Amp\Promise\wait;
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

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
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
                static function(string $element): ?string
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

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        wait(self::$adapter->execute('DROP TABLE event_store_stream CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_store_stream_events CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_store_snapshots CASCADE'));
        wait(self::$adapter->execute('DROP TABLE event_sourcing_indexes CASCADE'));

        self::$adapter = null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->indexesStore  = new SqlIndexStore(self::$adapter);
        $this->indexProvider = new IndexProvider($this->indexesStore);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('TRUNCATE TABLE event_sourcing_indexes CASCADE'));

        unset($this->indexesStore, $this->indexProvider);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function save(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        /** @var bool $result */
        $result = wait($this->indexProvider->add($index, $value));

        static::assertThat($result, new IsType('bool'));
        static::assertTrue($result);

        /** @var IndexValue|null $storedValue */
        $storedValue = wait($this->indexProvider->get($index));

        static::assertNotNull($storedValue);
        static::assertSame($value->value, $storedValue->value);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function saveDuplicate(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new  IndexValue(__METHOD__);

        /** @var bool $result */
        $result = wait($this->indexProvider->add($index, $value));

        static::assertThat($result, new IsType('bool'));
        static::assertTrue($result);

        /** @var bool $result */
        $result = wait($this->indexProvider->add($index, $value));

        static::assertThat($result, new IsType('bool'));
        static::assertFalse($result);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function update(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        wait($this->indexProvider->add($index, $value));

        $newValue = new IndexValue('qwerty');

        wait($this->indexProvider->update($index, $newValue));

        /** @var IndexValue|null $storedValue */
        $storedValue = wait($this->indexProvider->get($index));

        static::assertNotNull($storedValue);
        static::assertSame($newValue->value, $storedValue->value);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function remove(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        wait($this->indexProvider->add($index, $value));
        wait($this->indexProvider->remove($index));

        static::assertNull(wait($this->indexProvider->get($index)));
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function has(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        static::assertFalse(wait($this->indexProvider->has($index)));

        wait($this->indexProvider->add($index, $value));

        static::assertTrue(wait($this->indexProvider->has($index)));
    }
}
