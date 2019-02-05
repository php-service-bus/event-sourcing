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
use ServiceBus\EventSourcing\EventStream\Serializer\Exceptions\SerializeEventFailed;
use ServiceBus\MessageSerializer\Symfony\SymfonyMessageSerializer;

/**
 *
 */
final class DefaultEventSerializer implements EventSerializer
{
    /**
     * @var SymfonyMessageSerializer
     */
    private $serializer;


    public function __construct()
    {
        $this->serializer = new SymfonyMessageSerializer();
    }

    /**
     * @inheritDoc
     */
    public function serialize(Event $event): string
    {
        try
        {
            return $this->serializer->encode($event);
        }
        catch(\Throwable $throwable)
        {
            throw new SerializeEventFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function unserialize(string $eventClass, string $payload): Event
    {
        try
        {
            $event = $this->serializer->decode($payload);

            if($event instanceof Event)
            {
                return $event;
            }

            throw new \InvalidArgumentException('The string must be a serialized representation of the event');
        }
        catch(\Throwable $throwable)
        {
            throw new SerializeEventFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }
}
