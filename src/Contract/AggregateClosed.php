<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Contract;

use ServiceBus\EventSourcing\AggregateId;

/**
 * The aggregate (event stream) was marked as closed for modification.
 *
 * @psalm-immutable
 */
final class AggregateClosed
{
    /**
     * Aggregate identifier.
     *
     * @var string
     */
    public $id;

    /**
     * Aggregate identifier class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId>
     *
     * @var string
     */
    public $idClass;

    /**
     * Aggregate class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     *
     * @var string
     */
    public $aggregateClass;

    /**
     * Operation datetime.
     *
     * @var \DateTimeImmutable
     */
    public $datetime;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     */
    public function __construct(AggregateId $id, string $aggregateClass, \DateTimeImmutable $datetime)
    {
        /** @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $idClass */
        $idClass = (string) \get_class($id);

        $this->id             = $id->toString();
        $this->idClass        = $idClass;
        $this->aggregateClass = $aggregateClass;
        $this->datetime       = $datetime;
    }
}
