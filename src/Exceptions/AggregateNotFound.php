<?php

declare(strict_types=1);

namespace ServiceBus\EventSourcing\Exceptions;

use ServiceBus\EventSourcing\AggregateId;

final class AggregateNotFound extends \RuntimeException
{
    public function __construct(AggregateId $id)
    {
        parent::__construct(
            \sprintf(
                'Aggregate with id "%s:%s" was not found',
                $id->toString(),
                \get_class($id)
            )
        );
    }
}
