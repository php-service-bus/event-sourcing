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

use function ServiceBus\Common\now;
use ServiceBus\EventSourcing\AggregateId;

/**
 * The aggregate (event stream) was marked as closed for modification.
 *
 * @psalm-readonly
 */
final class AggregateClosed
{
    /**
     * Aggregate identifier.
     */
    public string $id;

    /**
     * Aggregate identifier class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId>
     */
    public string $idClass;

    /**
     * Aggregate class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     */
    public string $aggregateClass;

    /**
     * Operation datetime.
     */
    public \DateTimeImmutable $datetime;

    /**
     * @psalm-param class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     */
    public function __construct(AggregateId $id, string $aggregateClass)
    {
        /** @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $idClass */
        $idClass = (string) \get_class($id);

        $this->id             = $id->toString();
        $this->idClass        = $idClass;
        $this->aggregateClass = $aggregateClass;
        $this->datetime       = now();
    }
}
