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
 * New aggregate created.
 *
 * @psalm-readonly
 */
final class AggregateCreated
{
    /**
     * Aggregate identifier.
     *
     * @var string
     */
    public string $id;

    /**
     * Aggregate identifier class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId>
     *
     * @var string
     */
    public string $idClass;

    /**
     * Aggregate class.
     *
     * @psalm-var class-string<\ServiceBus\EventSourcing\Aggregate>
     *
     * @var string
     */
    public string $aggregateClass;

    /**
     * Operation datetime.
     *
     * @var \DateTimeImmutable
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
