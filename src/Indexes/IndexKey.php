<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Indexes;

use ServiceBus\EventSourcing\Indexes\Exceptions\IndexNameCantBeEmpty;
use ServiceBus\EventSourcing\Indexes\Exceptions\ValueKeyCantBeEmpty;

/**
 * The key for the value stored in the index.
 *
 * @psalm-readonly
 */
final class IndexKey
{
    /** @var string  */
    public $indexName;

    /** @var string  */
    public $valueKey;

    /**
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\IndexNameCantBeEmpty
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\ValueKeyCantBeEmpty
     */
    public function __construct(string $indexName, string $valueKey)
    {
        self::assertIndexNameIsNotEmpty($indexName);
        self::assertValueKeyIsNotEmpty($valueKey);

        $this->indexName = $indexName;
        $this->valueKey  = $valueKey;
    }

    /**
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\IndexNameCantBeEmpty
     */
    private static function assertIndexNameIsNotEmpty(string $indexName): void
    {
        if ('' === $indexName)
        {
            throw new IndexNameCantBeEmpty('Index name can\'t be empty');
        }
    }

    /**
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\ValueKeyCantBeEmpty
     */
    private static function assertValueKeyIsNotEmpty(string $valueKey): void
    {
        if ('' === $valueKey)
        {
            throw new ValueKeyCantBeEmpty('Value key can\'t be empty');
        }
    }
}
