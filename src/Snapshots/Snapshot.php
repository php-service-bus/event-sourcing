<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Snapshots;

use ServiceBus\EventSourcing\Aggregate;

/**
 * @psalm-immutable
 */
final class Snapshot
{
    /**
     * @psalm-readonly
     *
     * @var Aggregate
     */
    public $aggregate;

    /**
     * @psalm-readonly
     *
     * @var int
     */
    public $version;

    public function __construct(Aggregate $aggregate, int $version)
    {
        $this->aggregate = $aggregate;
        $this->version   = $version;
    }
}
