<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\EventSourcing\Indexes;

use ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType;

/**
 * The value stored in the index.
 *
 * @psalm-immutable
 */
final class IndexValue
{
    /**
     * @var int|float|string|bool
     */
    public $value;

    /**
     * @param int|float|string $value
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType
     */
    public function __construct(int|float|string|bool $value)
    {
        /** @psalm-suppress RedundantCondition */
        self::assertIsScalar($value);

        $this->value = $value;
    }

    /**
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType
     */
    private static function assertIsScalar(int|float|string|bool $value): void
    {
        /**
         * @psalm-suppress RedundantCondition
         * @psalm-suppress TypeDoesNotContainType
         */
        if (\is_scalar($value) === false)
        {
            throw new InvalidValueType(
                \sprintf('The value must be of type "scalar". "%s" passed', \gettype($value))
            );
        }
    }
}
