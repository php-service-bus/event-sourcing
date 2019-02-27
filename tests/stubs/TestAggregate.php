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

use ServiceBus\EventSourcing\Aggregate;

/**
 *
 */
final class TestAggregate extends Aggregate
{
    /**
     * @var string|null
     */
    private $firstValue;

    /**
     * @var string|null
     */
    private $secondValue;

    /**
     * @param string $value
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream
     *
     * @return void
     *
     */
    public function firstAction(string $value): void
    {
        $this->raise(new FirstEventWithKey($value));
    }

    /**
     * @param string $value
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream
     *
     * @return void
     *
     */
    public function secondAction(string $value): void
    {
        $this->raise(new SecondEventWithKey($value));
    }

    /**
     * @return string|null
     */
    public function firstValue(): ?string
    {
        return $this->firstValue;
    }

    /**
     * @return string|null
     */
    public function secondValue(): ?string
    {
        return $this->secondValue;
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @param SecondEventWithKey $event
     *
     * @return void
     */
    private function onSecondEventWithKey(SecondEventWithKey $event): void
    {
        $this->secondValue = $event->key();
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @param FirstEventWithKey $event
     *
     * @return void
     */
    private function onFirstEventWithKey(FirstEventWithKey $event): void
    {
        $this->firstValue = $event->key();
    }
}
