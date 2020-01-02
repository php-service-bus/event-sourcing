<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Indexes;

use ServiceBus\EventSourcing\Indexes\Exceptions\EmptyValuesNotAllowed;
use ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType;

/**
 * The value stored in the index.
 *
 * @psalm-immutable
 */
final class IndexValue
{
    /**
     * @var mixed
     */
    public $value;

    /**
     * @param mixed $value
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\EmptyValuesNotAllowed
     */
    public function __construct($value)
    {
        self::assertIsScalar($value);
        self::assertNotEmpty($value);

        $this->value = $value;
    }

    /**
     * @param mixed $value
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\EmptyValuesNotAllowed
     */
    private static function assertNotEmpty($value): void
    {
        if ('' === (string) $value)
        {
            throw new EmptyValuesNotAllowed('Value can not be empty');
        }
    }

    /**
     * @param mixed $value
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType
     */
    private static function assertIsScalar($value): void
    {
        if (false === \is_scalar($value))
        {
            throw new InvalidValueType(
                \sprintf('The value must be of type "scalar". "%s" passed', \gettype($value))
            );
        }
    }
}
