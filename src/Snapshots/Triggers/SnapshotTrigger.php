<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Snapshots\Triggers;

use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\Snapshots\Snapshot;

/**
 * Snapshot trigger.
 */
interface SnapshotTrigger
{
    /**
     * A snapshot must be created?
     *
     * @param Aggregate $aggregate
     * @param Snapshot  $previousSnapshot
     *
     * @return bool
     */
    public function snapshotMustBeCreated(Aggregate $aggregate, Snapshot $previousSnapshot = null): bool;
}
