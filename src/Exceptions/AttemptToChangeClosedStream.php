<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Exceptions;

use ServiceBus\EventSourcing\AggregateId;

/**
 * It is not allowed to modify the closed event stream.
 */
final class AttemptToChangeClosedStream extends \RuntimeException
{
    /**
     * @param AggregateId $id
     */
    public function __construct(AggregateId $id)
    {
        parent::__construct(
            \sprintf(
                'Can not add an event to a closed thread. Aggregate: "%s:%s"',
                $id->toString(),
                \get_class($id)
            )
        );
    }
}
