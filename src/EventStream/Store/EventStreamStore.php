<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream\Store;

use Amp\Promise;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;

/**
 *
 */
interface EventStreamStore
{
    /**
     * Save new event stream.
     *
     * @param StoredAggregateEventStream $aggregateEventStream
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     *
     * @return Promise It doesn't return any result
     */
    public function save(StoredAggregateEventStream $aggregateEventStream): Promise;

    /**
     * Append events to exists stream.
     *
     * @param StoredAggregateEventStream $aggregateEventStream
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     *
     * @return Promise It doesn't return any result
     */
    public function append(StoredAggregateEventStream $aggregateEventStream): Promise;

    /**
     * Load event stream.
     *
     * @param AggregateId $id
     * @param int         $fromVersion
     * @param int|null    $toVersion
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     *
     * @return Promise<\ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream|null>
     */
    public function load(
        AggregateId $id,
        int $fromVersion = Aggregate::START_PLAYHEAD_INDEX,
        ?int $toVersion = null
    ): Promise;

    /**
     * Marks stream closed.
     *
     * @param AggregateId $id
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     *
     * @return Promise It does not return any result
     */
    public function close(AggregateId $id): Promise;

    /**
     * Roll back all changes to specified version.
     *
     * @param AggregateId $id
     * @param int         $toVersion
     * @param bool        $force Delete all events after the specified version
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamDoesNotExist
     * @throws \ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamIntegrityCheckFailed
     *
     * @return Promise It doesn't return any result
     */
    public function revert(AggregateId $id, int $toVersion, bool $force): Promise;
}
