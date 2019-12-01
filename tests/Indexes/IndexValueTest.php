<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\Indexes;

use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Indexes\Exceptions\EmptyValuesNotAllowed;
use ServiceBus\EventSourcing\Indexes\Exceptions\InvalidValueType;
use ServiceBus\EventSourcing\Indexes\IndexValue;

/**
 *
 */
final class IndexValueTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function createWithWrongType(): void
    {
        $this->expectException(InvalidValueType::class);
        $this->expectExceptionMessage('The value must be of type "scalar". "object" passed');

        new IndexValue(
            static function(): void
            {
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function createWithEmptyValue(): void
    {
        $this->expectException(EmptyValuesNotAllowed::class);
        $this->expectExceptionMessage('Value can not be empty');

        new IndexValue('');
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function successCreate(): void
    {
        new IndexValue(0);
    }
}
