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

use function ServiceBus\Common\datetimeInstantiator;
use ServiceBus\EventSourcing\AggregateId;

/**
 * The aggregate (event stream) was marked as closed for modification.
 *
 * @property-read string             $id
 * @property-read string             $idClass
 * @property-read string             $aggregateClass
 * @property-read \DateTimeImmutable $datetime
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
     *
     * @param AggregateId $id
     * @param string      $aggregateClass
     *
     * @return self
     */
    public static function create(AggregateId $id, string $aggregateClass): self
    {
        /** @psalm-var class-string<\ServiceBus\EventSourcing\AggregateId> $idClass */
        $idClass = \get_class($id);

        return new self( $id->toString(), $idClass, $aggregateClass);
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @psalm-param  class-string<\ServiceBus\EventSourcing\AggregateId> $idClass
     * @psalm-param  class-string<\ServiceBus\EventSourcing\Aggregate> $aggregateClass
     *
     * @param string $id
     * @param string $idClass
     * @param string $aggregateClass
     */
    private function __construct(string $id, string $idClass, string $aggregateClass)
    {
        $this->id             = $id;
        $this->idClass        = $idClass;
        $this->aggregateClass = $aggregateClass;

        /**
         * @noinspection PhpUnhandledExceptionInspection
         *
         * @var \DateTimeImmutable
         */
        $currentDate = datetimeInstantiator('NOW');

        $this->datetime = $currentDate;
    }
}
