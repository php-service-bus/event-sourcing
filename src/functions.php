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

use ServiceBus\EventSourcing\Indexes\IndexKey;

/**
 * @internal
 */
function createAggregateMutexKey(AggregateId $id): string
{
    return \sha1(\sprintf('aggregate:%s', $id->toString()));
}

/**
 * @internal
 */
function createIndexMutex(IndexKey $indexKey): string
{
    return \sha1(\sprintf('index:%s', $indexKey->valueKey));
}
