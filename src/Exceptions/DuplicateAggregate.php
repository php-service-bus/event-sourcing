<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\EventSourcing\Exceptions;

use ServiceBus\EventSourcing\AggregateId;

/**
 *
 */
final class DuplicateAggregate extends \InvalidArgumentException
{
    public static function create(AggregateId $id): self
    {
        return new self(
            \sprintf('Aggregate with ID "%s" already exists', $id->toString())
        );
    }
}
