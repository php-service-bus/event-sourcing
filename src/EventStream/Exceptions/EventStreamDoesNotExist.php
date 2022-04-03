<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\EventStream\Exceptions;

use ServiceBus\EventSourcing\AggregateId;

/**
 *
 */
final class EventStreamDoesNotExist extends \InvalidArgumentException
{
    public static function create(AggregateId $id): self
    {
        return new self(\sprintf('Event stream with identifier "%s" doesn\'t exist', $id->toString()));
    }
}
