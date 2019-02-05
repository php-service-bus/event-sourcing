<?php

/**
 * Event Sourcing implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\EventStream\Serializer;

use ServiceBus\Common\Messages\Event;

/**
 *
 */
interface EventSerializer
{
    /**
     * Serialize event object to string
     *
     * @param Event $event
     *
     * @return string
     *
     * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
     */
    public function serialize(Event $event): string;

    /**
     * Restore event object
     *
     * @psalm-param class-string<\ServiceBus\Common\Messages\Event> $eventClass
     *
     * @param string $eventClass
     * @param string $payload
     *
     * @return Event
     *
     * @throws \ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed
     */
    public function unserialize(string $eventClass, string $payload): Event;
}
