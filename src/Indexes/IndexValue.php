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
 * @property-read mixed $value
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
     *
     * @return self
     */
    public static function create($value): self
    {
        self::assertIsScalar($value);
        self::assertNotEmpty($value);

        return new self($value);
    }

    /**
     * @param mixed $value
     *
     * @throws \ServiceBus\EventSourcing\Indexes\Exceptions\EmptyValuesNotAllowed
     *
     * @return void
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
     *
     * @return void
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

    /**
     * @param mixed $value
     */
    private function __construct($value)
    {
        $this->value = $value;
    }
}
