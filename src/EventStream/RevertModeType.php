<?php

declare(strict_types=1);

namespace ServiceBus\EventSourcing\EventStream;

enum RevertModeType: int
{
    /** Mark tail events as deleted (soft deletion). There may be version conflicts in some situations */
    case SOFT_DELETE = 1;

    /** Removes tail events from the database (the best option) */
    case DELETE = 2;
}
