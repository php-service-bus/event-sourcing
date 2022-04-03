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
     * @throws \ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream
     */
    public function firstAction(string $value): void
    {
        $this->raise(new FirstEventWithKey($value));
    }

    /**
     * @throws \ServiceBus\EventSourcing\Exceptions\AttemptToChangeClosedStream
     */
    public function secondAction(string $value): void
    {
        $this->raise(new SecondEventWithKey($value));
    }

    public function firstValue(): ?string
    {
        return $this->firstValue;
    }

    public function secondValue(): ?string
    {
        return $this->secondValue;
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function onSecondEventWithKey(SecondEventWithKey $event): void
    {
        $this->secondValue = $event->key();
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function onFirstEventWithKey(FirstEventWithKey $event): void
    {
        $this->firstValue = $event->key();
    }
}
