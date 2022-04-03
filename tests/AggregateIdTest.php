<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\EventSourcing\Tests;

use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Exceptions\InvalidAggregateIdentifier;
use ServiceBus\EventSourcing\Tests\stubs\TestAggregateId;

/**
 *
 */
final class AggregateIdTest extends TestCase
{
    /**
     * @test
     */
    public function createWithEmptyId(): void
    {
        $this->expectException(InvalidAggregateIdentifier::class);
        $this->expectExceptionMessage('The aggregate identifier can\'t be empty');

        /** @noinspection PhpExpressionResultUnusedInspection */
        new TestAggregateId('');
    }
}
