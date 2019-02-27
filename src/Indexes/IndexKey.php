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
 * @property-read string $indexName
 * @property-read string $valueKey
 */
final class IndexKey
{
    /**
     * @var string
     */
    public $indexName;

    /**
     * @var string
     */
    public $valueKey;

    /**
     * @param string $indexName
     * @param string $valueKey
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\IndexNameCantBeEmpty
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\ValueKeyCantBeEmpty
     *
     * @return self
     */
    public static function create(string $indexName, string $valueKey): self
    {
        self::assertIndexNameIsNotEmpty($indexName);
        self::assertValueKeyIsNotEmpty($valueKey);

        return new self($indexName, $valueKey);
    }

    /**
     * @param string $indexName
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\IndexNameCantBeEmpty
     *
     * @return void
     *
     */
    private static function assertIndexNameIsNotEmpty(string $indexName): void
    {
        if ('' === $indexName)
        {
            throw new IndexNameCantBeEmpty('Index name can\'t be empty');
        }
    }

    /**
     * @param string $valueKey
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\ValueKeyCantBeEmpty
     *
     * @return void
     */
    private static function assertValueKeyIsNotEmpty(string $valueKey): void
    {
        if ('' === $valueKey)
        {
            throw new ValueKeyCantBeEmpty('Value key can\'t be empty');
        }
    }

    /**
     * @param string $indexName
     * @param string $valueKey
     */
    private function __construct(string $indexName, string $valueKey)
    {
        $this->indexName = $indexName;
        $this->valueKey  = $valueKey;
    }
}
