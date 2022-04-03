<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing;

use ServiceBus\EventSourcing\Exceptions\InvalidAggregateIdentifier;
use function ServiceBus\Common\uuid;

/**
 * Base aggregate identifier class.
 *
 * @psalm-immutable
 */
abstract class AggregateId
{
    /**
     * Identifier.
     *
     * @psalm-var non-empty-string
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
        if ($id === '')
        {
            throw InvalidAggregateIdentifier::emptyId();
        }

        $this->id = $id;
    }

    /**
     * @psalm-return non-empty-string
     */
    public function toString(): string
    {
        return $this->id;
    }
}
