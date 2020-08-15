<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Exceptions;

/**
 *
 */
final class IndexOperationFailed extends \RuntimeException
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
