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

/**
 * @internal
 *
 * @psalm-return non-empty-string
 */
function createAggregateMutexKey(AggregateId $id): string
{
    /** @psalm-var non-empty-string $key */
    $key = \sha1(\sprintf('aggregate:%s', $id->toString()));

    return $key;
}
