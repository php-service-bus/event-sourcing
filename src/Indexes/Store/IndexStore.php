<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Indexes\Store;

use Amp\Promise;
use ServiceBus\EventSourcing\Indexes\IndexKey;
use ServiceBus\EventSourcing\Indexes\IndexValue;

/**
 *
 */
interface IndexStore
{
    /**
     * Find stored value.
     *
     * Returns \ServiceBus\EventSourcing\Indexes\IndexValue|null
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function find(IndexKey $indexKey): Promise;

    /**
     * Add a new value.
     *
     * Returns the number of added records
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    public function add(IndexKey $indexKey, IndexValue $value): Promise;

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function delete(IndexKey $indexKey): Promise;

    /**
     * Update existent value.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function update(IndexKey $indexKey, IndexValue $value): Promise;
}
