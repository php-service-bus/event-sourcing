<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

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
     */
    public function snapshotMustBeCreated(Aggregate $aggregate, Snapshot $previousSnapshot = null): bool;
}
