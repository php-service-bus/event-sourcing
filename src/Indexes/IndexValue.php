<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Indexes;

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
        $this->value = $value;
    }
}
