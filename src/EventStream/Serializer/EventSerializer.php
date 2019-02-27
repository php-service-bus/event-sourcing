<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream\Serializer;

/**
 *
 */
interface EventSerializer
{
    /**
     * Serialize event object to string.
     *
     * @param object $event
     *
     * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
     *
     * @return string
     */
    public function serialize(object $event): string;

    /**
     * Restore event object.
     *
     * @psalm-param class-string $eventClass
     *
     * @param string $eventClass
     * @param string $payload
     *
     * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
     *
     * @return object
     */
    public function unserialize(string $eventClass, string $payload): object;
}
