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
 * Generation of snapshots every N versions.
 */
class SnapshotVersionTrigger implements SnapshotTrigger
{
    private const DEFAULT_VERSION_STEP = 10;

    /**
     * Version step interval.
     */
    private int $stepInterval;

    public function __construct(int $stepInterval = self::DEFAULT_VERSION_STEP)
    {
        $this->stepInterval = $stepInterval;
    }

    /**
     * {@inheritdoc}
     */
    public function snapshotMustBeCreated(Aggregate $aggregate, Snapshot $previousSnapshot = null): bool
    {
        if (null === $previousSnapshot)
        {
            return true;
        }

        return $this->stepInterval <= ($aggregate->version() - $previousSnapshot->version);
    }
}
