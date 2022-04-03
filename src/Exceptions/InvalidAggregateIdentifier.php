<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Exceptions;

/**
 *
 */
final class InvalidAggregateIdentifier extends \RuntimeException
{
    public static function emptyId(): self
    {
        return new self('The aggregate identifier can\'t be empty');
    }
}
