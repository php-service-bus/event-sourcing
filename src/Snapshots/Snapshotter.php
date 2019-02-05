<?php

/**
 * Event Sourcing implementation
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
    /**
     * Snapshot storage
     *
     * @var SnapshotStore
     */
    private $store;

    /**
     * Snapshot generation trigger
     *
     * @var SnapshotTrigger
     */
    private $trigger;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SnapshotStore        $store
     * @param SnapshotTrigger      $trigger
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        SnapshotStore $store,
        SnapshotTrigger $trigger,
        LoggerInterface $logger = null
    )
    {
        $this->store   = $store;
        $this->trigger = $trigger;
        $this->logger  = $logger ?? new NullLogger();
    }

    /**
     * Load snapshot for aggregate
     *
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * @param AggregateId $id
     *
     * @return Promise<\ServiceBus\EventSourcing\Snapshots\Snapshot|null>
     */
    public function load(AggregateId $id): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(AggregateId $id): \Generator
            {
                $snapshot = null;

                try
                {
                    /** @var Snapshot|null $snapshot */
                    $snapshot = yield $this->store->load($id);
                }
                catch(\Throwable $throwable)
                {
                    $this->logger->error($throwable->getMessage(), ['e' => $throwable]);
                }

                return $snapshot;
            },
            $id
        );
    }

    /**
     * Store new snapshot
     *
     * @param Snapshot $snapshot
     *
     * @return Promise It doesn't return any result
     */
    public function store(Snapshot $snapshot): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Snapshot $snapshot): \Generator
            {
                try
                {
                    yield $this->store->remove($snapshot->aggregate->id());
                    yield $this->store->save($snapshot);
                }
                catch(\Throwable $throwable)
                {
                    $this->logger->error($throwable->getMessage(), ['e' => $throwable]);
                }
            },
            $snapshot
        );
    }

    /**
     * A snapshot must be created
     *
     * @param Aggregate $aggregate
     * @param Snapshot  $previousSnapshot
     *
     * @return bool
     */
    public function snapshotMustBeCreated(Aggregate $aggregate, Snapshot $previousSnapshot = null): bool
    {
        return $this->trigger->snapshotMustBeCreated($aggregate, $previousSnapshot);
    }
}
