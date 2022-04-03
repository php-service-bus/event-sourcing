<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Contract;

use ServiceBus\EventSourcing\AggregateId;

/**
 * New aggregate created.
 *
 * @psalm-immutable
 */
final class AggregateCreated
{
    /**
     * Aggregate identifier.
     *
     * @psalm-readonly
     *
     * @var string
     */
    public $id;

    /**
     * Aggregate identifier class.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId>
     *
     * @var string
     */
    public $idClass;

    /**
     * Aggregate class.
     *
     * @psalm-readonly
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     *
     * @var string
     */
    public $aggregateClass;

    /**
     * Operation datetime.
     *
     * @psalm-readonly
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     */
    public function __construct(AggregateId $id, string $aggregateClass, \DateTimeImmutable $datetime)
    {
        $this->id             = $id->toString();
        $this->idClass        = \get_class($id);
        $this->aggregateClass = $aggregateClass;
        $this->datetime       = $datetime;
    }
}
