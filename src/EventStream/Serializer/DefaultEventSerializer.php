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
     * {@inheritdoc}
     */
    public function serialize(object $event): string
    {
        try
        {
            return $this->serializer->encode($event);
        }
        catch (\Throwable $throwable)
        {
            throw SerializeEventFailed::fromThrowable($throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize(string $eventClass, string $payload): object
    {
        try
        {
            return $this->serializer->decode($payload);
        }
        catch (\Throwable $throwable)
        {
            throw SerializeEventFailed::fromThrowable($throwable);
        }
    }
}
