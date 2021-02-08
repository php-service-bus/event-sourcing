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
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        /** @var bool $result */
        $result = wait($this->indexProvider->add($index, $value));

        self::assertThat($result, new IsType('bool'));
        self::assertTrue($result);

        /** @var IndexValue|null $storedValue */
        $storedValue = wait($this->indexProvider->get($index));

        self::assertNotNull($storedValue);
        self::assertSame($value->value, $storedValue->value);
    }

    /**
     * @test
     */
    public function saveDuplicate(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new  IndexValue(__METHOD__);

        /** @var bool $result */
        $result = wait($this->indexProvider->add($index, $value));

        self::assertThat($result, new IsType('bool'));
        self::assertTrue($result);

        /** @var bool $result */
        $result = wait($this->indexProvider->add($index, $value));

        self::assertThat($result, new IsType('bool'));
        self::assertFalse($result);
    }

    /**
     * @test
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

        self::assertNotNull($storedValue);
        self::assertSame($newValue->value, $storedValue->value);
    }

    /**
     * @test
     */
    public function remove(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        wait($this->indexProvider->add($index, $value));
        wait($this->indexProvider->remove($index));

        self::assertNull(wait($this->indexProvider->get($index)));
    }

    /**
     * @test
     */
    public function has(): void
    {
        $index = new IndexKey(__CLASS__, 'testKey');
        $value = new IndexValue(__METHOD__);

        self::assertFalse(wait($this->indexProvider->has($index)));

        wait($this->indexProvider->add($index, $value));

        self::assertTrue(wait($this->indexProvider->has($index)));
    }
}
