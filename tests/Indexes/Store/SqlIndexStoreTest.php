<?php

/**
 * Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\Indexes\Store;

use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Indexes\IndexKey;
use ServiceBus\EventSourcing\Indexes\IndexValue;
use ServiceBus\EventSourcing\Indexes\Store\SqlIndexStore;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;

/**
 *
 */
final class SqlIndexStoreTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private static $adapter;

    /**
     * @var SqlIndexStore
     */
    private $indexStore;

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

        wait(
            self::$adapter->execute(
                \file_get_contents(__DIR__ . '/../../../src/Indexes/Store/schema/event_sourcing_indexes.sql')
            )
        );
    }

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        wait(self::$adapter->execute('DROP TABLE IF EXISTS event_sourcing_indexes CASCADE'));

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

        $this->indexStore = new SqlIndexStore(self::$adapter);
    }

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('TRUNCATE TABLE event_sourcing_indexes CASCADE'));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function save(): void
    {
        $index = IndexKey::create(__CLASS__, 'testKey');
        $value = IndexValue::create(__METHOD__);

        /** @var int $count */
        $count = wait($this->indexStore->add($index, $value));

        static::assertSame(1, $count);

        /** @var IndexValue|null $storedValue */
        $storedValue = wait($this->indexStore->find($index));

        static::assertNotNull($storedValue);
        static::assertEquals($value->value, $storedValue->value);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function saveDuplicate(): void
    {
        $this->expectException(UniqueConstraintViolationCheckFailed::class);

        $index = IndexKey::create(__CLASS__, 'testKey');
        $value = IndexValue::create(__METHOD__);

        wait($this->indexStore->add($index, $value));
        wait($this->indexStore->add($index, $value));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function update(): void
    {
        $index = IndexKey::create(__CLASS__, 'testKey');
        $value = IndexValue::create(__METHOD__);

        wait($this->indexStore->add($index, $value));

        $newValue = IndexValue::create('qwerty');

        wait($this->indexStore->update($index, $newValue));

        /** @var IndexValue|null $storedValue */
        $storedValue = wait($this->indexStore->find($index));

        static::assertNotNull($storedValue);
        static::assertEquals($newValue->value, $storedValue->value);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function remove(): void
    {
        $index = IndexKey::create(__CLASS__, 'testKey');
        $value = IndexValue::create(__METHOD__);

        wait($this->indexStore->add($index, $value));
        wait($this->indexStore->delete($index));

        static::assertNull(wait($this->indexStore->find($index)));
    }
}
