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
final class RevertAggregateVersionFailed extends \RuntimeException
{
    public static function fromThrowable(\Throwable $throwable): self
    {
        return new self(
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            $throwable
        );
    }
}
