<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\EventStream\Serializer;

use PHPUnit\Framework\TestCase;
use ServiceBus\MessageSerializer\Exceptions\DecodeMessageFailed;
use ServiceBus\MessageSerializer\Symfony\SymfonySerializer;

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
        $this->expectException(DecodeMessageFailed::class);
        $this->expectExceptionMessage('Message deserialization failed: Syntax error');

        $serializer = new SymfonySerializer();

        $serializer->decode('', 'qwerty');
    }
}
