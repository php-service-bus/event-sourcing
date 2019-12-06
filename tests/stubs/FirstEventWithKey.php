<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\stubs;

/**
 *
 */
final class FirstEventWithKey
{
    /** @var string  */
    private $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function key(): string
    {
        return $this->key;
    }
}
