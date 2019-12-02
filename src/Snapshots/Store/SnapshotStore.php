<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Snapshots\Store;

use Amp\Promise;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\Snapshots\Snapshot;

/**
 *
 */
interface SnapshotStore
{
    /**
     * Save snapshot.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     */
    public function save(Snapshot $snapshot): Promise;

    /**
     * Load snapshot.
     *
     * Returns \ServiceBus\EventSourcing\Snapshots\Snapshot|null
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function load(AggregateId $id): Promise;

    /**
     * Remove snapshot from database.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function remove(AggregateId $id): Promise;
}
