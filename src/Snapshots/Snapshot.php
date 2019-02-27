<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Snapshots;

use ServiceBus\EventSourcing\Aggregate;

/**
 * @property-read Aggregate $aggregate
 * @property-read int       $version
 */
final class Snapshot
{
    /**
     * Aggregate.
     *
     * @var Aggregate
     */
    public $aggregate;

    /**
     * Aggregate version.
     *
     * @var int
     */
    public $version;

    /**
     * @param Aggregate $aggregate
     * @param int       $version
     *
     * @return self
     */
    public static function create(Aggregate $aggregate, int $version): self
    {
        return new self($aggregate, $version);
    }

    /**
     * @param Aggregate $aggregate
     * @param int       $version
     */
    public function __construct(Aggregate $aggregate, int $version)
    {
        $this->aggregate = $aggregate;
        $this->version   = $version;
    }
}
