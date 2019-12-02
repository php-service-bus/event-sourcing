<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Snapshots;

use function Amp\call;
use Amp\Promise;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\EventSourcing\Aggregate;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\Snapshots\Store\SnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotTrigger;

/**
 *
 */
final class Snapshotter
{
    private SnapshotStore $store;

    /**
     * Snapshot generation trigger.
     */
    private SnapshotTrigger $trigger;

    private LoggerInterface $logger;

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
     * Returns \ServiceBus\EventSourcing\Snapshots\Snapshot|null
     */
    public function load(AggregateId $id): Promise
    {
        $store  = $this->store;
        $logger = $this->logger;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id) use ($store, $logger): \Generator
            {
                $snapshot = null;

                try
                {
                    /** @var Snapshot|null $snapshot */
                    $snapshot = yield $store->load($id);
                }
                catch (\Throwable $throwable)
                {
                    $logger->error(
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
            },
            $id
        );
    }

    /**
     * Store new snapshot.
     */
    public function store(Snapshot $snapshot): Promise
    {
        $store  = $this->store;
        $logger = $this->logger;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(Snapshot $snapshot) use ($store, $logger): \Generator
            {
                $id = $snapshot->aggregate->id();

                try
                {
                    yield $store->remove($id);
                    yield $store->save($snapshot);
                }
                catch (\Throwable $throwable)
                {
                    $logger->error(
                        'Error saving snapshot of aggregate with identifier "{aggregateIdClass}:{aggregateId}"',
                        [
                            'aggregateIdClass' => \get_class($id),
                            'aggregateId'      => $id->toString(),
                            'throwableMessage' => $throwable->getMessage(),
                            'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                        ]
                    );
                }
                finally
                {
                    unset($id);
                }
            },
            $snapshot
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
