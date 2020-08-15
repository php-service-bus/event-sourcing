<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\stubs;

use function ServiceBus\Common\uuid;
use Amp\Promise;
use Amp\Success;
use Psr\Log\LogLevel;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\Endpoint\DeliveryOptions;

/**
 *
 */
final class Context implements ServiceBusContext
{
    public $messages = [];

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function violations(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function delivery(object $message, ?DeliveryOptions $deliveryOptions = null): Promise
    {
        $this->messages[] = $message;

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function logContextMessage(string $logMessage, array $extra = [], string $level = LogLevel::INFO): void
    {
        // TODO: Implement logContextMessage() method.
    }

    /**
     * {@inheritdoc}
     */
    public function logContextThrowable(\Throwable $throwable, array $extra = [], string $level = LogLevel::ERROR): void
    {
        // TODO: Implement logContextThrowable() method.
    }

    /**
     * {@inheritdoc}
     */
    public function return(int $secondsDelay = 3): Promise
    {
        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function operationId(): string
    {
        return uuid();
    }

    /**
     * {@inheritdoc}
     */
    public function traceId(): string
    {
        return uuid();
    }
}
