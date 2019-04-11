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
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @return static
     */
    public static function new(): self
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new static(uuid());
    }

    /**
     * @param string $id
     *
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

    /**
     * @return string
     */
    public function toString(): string
    {
        return $this->id;
    }

    /**
     * @deprecated
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->id;
    }
}
