<?php

/**
 * Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\EventStream\Serializer;

use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\EventStream\Serializer\DefaultEventSerializer;
use ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed;

/**
 *
 */
final class DefaultEventSerializerTest extends TestCase
{
    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function unserializeWrongMessageType(): void
    {
        $this->expectException(SerializeEventFailed::class);
        $this->expectExceptionMessage('JSON unserialize failed: Syntax error');

        $serializer = new DefaultEventSerializer();

        $serializer->unserialize('', 'qwerty');
    }
}
