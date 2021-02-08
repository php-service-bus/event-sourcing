<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\Indexes\Store;

use function Amp\Promise\wait;
use Amp\Loop;
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
     * @var DatabaseAdapter|null
     */
    private static $adapter;

    /**
     * @var SqlIndexStore
     */
    private $indexStore;

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

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        wait(self::$adapter->execute('DROP TABLE IF EXISTS event_sourcing_indexes CASCADE'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexStore = new SqlIndexStore(self::$adapter);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('TRUNCATE TABLE event_sourcing_indexes CASCADE'));
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

                /** @var int $count */
                $count = yield $this->indexStore->add($index, $value);

                self::assertSame(1, $count);

                /** @var IndexValue|null $storedValue */
                $storedValue = yield $this->indexStore->find($index);

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
                $this->expectException(UniqueConstraintViolationCheckFailed::class);

                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new IndexValue(__METHOD__);

                yield $this->indexStore->add($index, $value);
                yield $this->indexStore->add($index, $value);
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

                yield $this->indexStore->add($index, $value);

                $newValue = new IndexValue('qwerty');

                yield $this->indexStore->update($index, $newValue);

                /** @var IndexValue|null $storedValue */
                $storedValue = yield $this->indexStore->find($index);

                self::assertNotNull($storedValue);
                self::assertSame($newValue->value, $storedValue->value);
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function remove(): void
    {
        Loop::run(
            function (): \Generator
            {
                $index = new IndexKey(__CLASS__, 'testKey');
                $value = new IndexValue(__METHOD__);

                yield $this->indexStore->add($index, $value);
                yield $this->indexStore->delete($index);

                self::assertNull(wait($this->indexStore->find($index)));
            }
        );
    }
}
