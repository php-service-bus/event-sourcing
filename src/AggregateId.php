<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing;

use function ServiceBus\Common\uuid;
use ServiceBus\EventSourcing\Exceptions\InvalidAggregateIdentifier;

/**
 * Base aggregate identifier class.
 */
abstract class AggregateId
{
    /**
     * Identifier.
     *
     * @var string
     */
    private $id;

    /**
     * @return static
     */
    public static function new(): self
    {
        return new static(uuid());
    }

    /**
     * @throws \ServiceBus\EventSourcing\Exceptions\InvalidAggregateIdentifier
     */
    final public function __construct(string $id)
    {
        if ('' === $id)
        {
            throw InvalidAggregateIdentifier::emptyId();
        }

        $this->id = $id;
    }

    public function toString(): string
    {
        return $this->id;
    }
}
