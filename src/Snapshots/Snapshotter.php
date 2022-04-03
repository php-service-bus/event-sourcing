<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Snapshots;

use Amp\Promise;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\Snapshots\Store\SnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotTrigger;
use function Amp\call;

/**
 *
 */
final class Snapshotter
{
    /**
     * @var SnapshotStore
     */
    private $store;

    /**
     * Snapshot generation trigger.
     *
     * @var SnapshotTrigger
     */
    private $trigger;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        SnapshotStore $store,
        SnapshotTrigger $trigger,
        LoggerInterface $logger = null
    ) {
        $this->store   = $store;
        $this->trigger = $trigger;
        $this->logger  = $logger ?? new NullLogger();
    }

    /**
     * Load snapshot for aggregate.
     *
     * @return Promise<\ServiceBus\EventSourcing\Snapshots\Snapshot|null>
     */
    public function load(AggregateId $id): Promise
    {
        return call(
            function () use ($id): \Generator
            {
                $snapshot = null;

                try
                {
                    /** @var Snapshot|null $snapshot */
                    $snapshot = yield $this->store->load($id);
                }
                catch (\Throwable $throwable)
                {
                    $this->logger->error(
                        'Error loading snapshot of aggregate with identifier "{aggregateIdClass}:{aggregateId}"',
                        [
                            'aggregateIdClass' => \get_class($id),
                            'aggregateId'      => $id->toString(),
                            'throwableMessage' => $throwable->getMessage(),
                            'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                        ]
                    );
                }

                return $snapshot;
            }
        );
    }

    /**
     * Store new snapshot.
     *
     * @return Promise<void>
     */
    public function store(Snapshot $snapshot): Promise
    {
        return call(
            function () use ($snapshot): \Generator
            {
                $id = $snapshot->aggregate->id();

                try
                {
                    yield $this->store->remove($id);
                    yield $this->store->save($snapshot);
                }
                catch (\Throwable $throwable)
                {
                    $this->logger->error(
                        'Error saving snapshot of aggregate with identifier "{aggregateIdClass}:{aggregateId}"',
                        [
                            'aggregateIdClass' => \get_class($id),
                            'aggregateId'      => $id->toString(),
                            'throwableMessage' => $throwable->getMessage(),
                            'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                        ]
                    );
                }
            }
        );
    }

    /**
     * A snapshot must be created.
     */
    public function snapshotMustBeCreated(Aggregate $aggregate, Snapshot $previousSnapshot = null): bool
    {
        return $this->trigger->snapshotMustBeCreated($aggregate, $previousSnapshot);
    }
}
