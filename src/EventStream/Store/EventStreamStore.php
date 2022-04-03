<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\EventStream\Store;

use Amp\Promise;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;

interface EventStreamStore
{
    /**
     * Save new event stream.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    public function save(StoredAggregateEventStream $aggregateEventStream): Promise;

    /**
     * Append events to exists stream.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function append(StoredAggregateEventStream $aggregateEventStream): Promise;

    /**
     * Load event stream.
     *
     * @psalm-return Promise<\ServiceBus\EventSourcing\EventStream\Store\StoredAggregateEventStream|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function load(
        AggregateId $id,
        int $fromVersion = Aggregate::START_PLAYHEAD_INDEX,
        ?int $toVersion = null
    ): Promise;

    /**
     * Marks stream closed.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function close(AggregateId $id): Promise;

    /**
     * Roll back all changes to specified version.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamDoesNotExist
     * @throws \ServiceBus\EventSourcing\EventStream\Exceptions\EventStreamIntegrityCheckFailed
     */
    public function revert(AggregateId $id, int $toVersion, bool $force): Promise;
}
