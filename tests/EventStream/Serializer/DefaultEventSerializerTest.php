<?php

/**
 * Event Sourcing implementation.
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
use ServiceBus\MessageSerializer\Symfony\SymfonyMessageSerializer;

/**
 *
 */
final class DefaultEventSerializerTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function unserializeWrongMessageType(): void
    {
        $this->expectException(SerializeEventFailed::class);
        $this->expectExceptionMessage('Message deserialization failed: Syntax error');

        $serializer = new DefaultEventSerializer(
            new SymfonyMessageSerializer()
        );

        $serializer->unserialize('', 'qwerty');
    }
}
