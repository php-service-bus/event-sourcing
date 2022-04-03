<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\EventSourcing\Tests\stubs;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use ServiceBus\Common\Context\ContextLogger;
use ServiceBus\Common\Context\IncomingMessageMetadata;
use ServiceBus\Common\Context\OutcomeMessageMetadata;
use ServiceBus\Common\Context\ValidationViolations;
use Amp\Promise;
use Amp\Success;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\Endpoint\DeliveryOptions;
use function ServiceBus\Common\uuid;

/**
 *
 */
final class Context implements ServiceBusContext
{
    /**
     * @var object[]
     */
    public $messages = [];

    /**
     * @var TestHandler
     */
    public $testLogHandler;

    public function violations(): ?ValidationViolations
    {
        return null;
    }

    public function delivery(object $message, ?DeliveryOptions $deliveryOptions = null, ?OutcomeMessageMetadata $withMetadata = null): Promise
    {
        $this->messages[] = $message;

        return new Success();
    }

    public function deliveryBulk(array $messages, ?DeliveryOptions $deliveryOptions = null, ?OutcomeMessageMetadata $withMetadata = null): Promise
    {
        $this->messages = \array_merge($this->messages, $messages);

        return new Success();
    }

    public function return(int $secondsDelay = 3, ?OutcomeMessageMetadata $withMetadata = null): Promise
    {
        return new Success();
    }

    public function logger(): ContextLogger
    {
        return new DefaultContextLogger(
            new Logger('tests', [$this->testLogHandler]),
            new \stdClass(),
            $this->metadata()
        );
    }

    public function headers(): array
    {
        return [];
    }

    public function metadata(): IncomingMessageMetadata
    {
        return ReceivedMessageMetadata::create(uuid(), []);
    }

    public function __construct()
    {
        $this->testLogHandler = new TestHandler();
    }
}
