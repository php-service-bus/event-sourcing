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
 * Generation of snapshots every N versions.
 */
class SnapshotVersionTrigger implements SnapshotTrigger
{
    private const DEFAULT_VERSION_STEP = 10;

    /**
     * Version step interval.
     *
     * @var int
     */
    private $stepInterval;

    public function __construct(int $stepInterval = self::DEFAULT_VERSION_STEP)
    {
        $this->stepInterval = $stepInterval;
    }

    public function snapshotMustBeCreated(Aggregate $aggregate, Snapshot $previousSnapshot = null): bool
    {
        if ($previousSnapshot === null)
        {
            return true;
        }

        return $this->stepInterval <= ($aggregate->version() - $previousSnapshot->version);
    }
}
