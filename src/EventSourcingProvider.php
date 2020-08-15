<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing;

use function Amp\call;
use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\EventSourcing\EventStream\EventStreamRepository;
use ServiceBus\EventSourcing\Exceptions\DuplicateAggregate;
use ServiceBus\EventSourcing\Exceptions\LoadAggregateFailed;
use ServiceBus\EventSourcing\Exceptions\RevertAggregateVersionFailed;
use ServiceBus\EventSourcing\Exceptions\SaveAggregateFailed;
use ServiceBus\Mutex\InMemory\InMemoryMutexFactory;
use ServiceBus\Mutex\Lock;
use ServiceBus\Mutex\MutexFactory;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;

/**
 *
 */
final class EventSourcingProvider
{
    /** @var EventStreamRepository */
    private $repository;

    /**
     * List of loaded/added aggregates.
     *
     * @psalm-var array<string, string>
     *
     * @var string[]
     */
    private $aggregates = [];

    /** @var MutexFactory */
    private $mutexFactory;

    /** @var Lock[] */
    private $lockCollection = [];

    public function __construct(EventStreamRepository $repository, ?MutexFactory $mutexFactory = null)
    {
        $this->repository   = $repository;
        $this->mutexFactory = $mutexFactory ?? new InMemoryMutexFactory();
    }

    /**
     * Load aggregate.
     *
     * Returns \ServiceBus\EventSourcing\Aggregate|null
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\LoadAggregateFailed
     */
    public function load(AggregateId $id): Promise
    {
        return call(
            function() use ($id): \Generator
            {
                yield from $this->setupMutex($id);

                try
                {
                    /** @var Aggregate|null $aggregate */
                    $aggregate = yield $this->repository->load($id);

                    if (null !== $aggregate)
                    {
                        $this->aggregates[$aggregate->id()->toString()] = \get_class($aggregate);
                    }

                    return $aggregate;
                }
                catch (\Throwable $throwable)
                {
                    throw LoadAggregateFailed::fromThrowable($throwable);
                }
                finally
                {
                    yield from $this->releaseMutex($id);
                }
            }
        );
    }

    /**
     * Save a new aggregate.
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\SaveAggregateFailed
     * @throws \ServiceBus\EventSourcing\Exceptions\DuplicateAggregate
     */
    public function save(Aggregate $aggregate, ServiceBusContext $context): Promise
    {
        return call(
            function() use ($aggregate, $context): \Generator
            {
                try
                {
                    /** The aggregate hasn't been loaded before, which means it is new */
                    if (false === isset($this->aggregates[$aggregate->id()->toString()]))
                    {
                        /**
                         * @psalm-var  array<int, object> $events
                         *
                         * @var object[] $events
                         */
                        $events = yield $this->repository->save($aggregate);

                        $this->aggregates[$aggregate->id()->toString()] = \get_class($aggregate);
                    }
                    else
                    {
                        /**
                         * @psalm-var array<int, object> $events
                         *
                         * @var object[] $events
                         */
                        $events = yield $this->repository->update($aggregate);
                    }

                    $promises = [];

                    foreach ($events as $event)
                    {
                        $promises[] = $context->delivery($event);
                    }

                    yield $promises;
                }
                catch (UniqueConstraintViolationCheckFailed $exception)
                {
                    throw DuplicateAggregate::create($aggregate->id());
                }
                catch (\Throwable $throwable)
                {
                    throw SaveAggregateFailed::fromThrowable($throwable);
                }
                finally
                {
                    yield from $this->releaseMutex($aggregate->id());
                }
            }
        );
    }

    /**
     * Revert aggregate to specified version.
     *
     * Returns \ServiceBus\EventSourcing\Aggregate
     *
     * Mode options:
     *   - 1 (EventStreamRepository::REVERT_MODE_SOFT_DELETE): Mark tail events as deleted (soft deletion). There may
     *   be version conflicts in some situations
     *   - 2 (EventStreamRepository::REVERT_MODE_DELETE): Removes tail events from the database (the best option)
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\RevertAggregateVersionFailed
     */
    public function revert(
        Aggregate $aggregate,
        int $toVersion,
        ?int $mode = null
    ): Promise {
        $mode = $mode ?? EventStreamRepository::REVERT_MODE_SOFT_DELETE;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function() use ($aggregate, $toVersion, $mode): \Generator
            {
                yield from $this->setupMutex($aggregate->id());

                try
                {
                    /** @var Aggregate $aggregate */
                    $aggregate = yield $this->repository->revert($aggregate, $toVersion, $mode);

                    return $aggregate;
                }
                catch (\Throwable $throwable)
                {
                    throw RevertAggregateVersionFailed::fromThrowable($throwable);
                }
                finally
                {
                    yield from $this->releaseMutex($aggregate->id());
                }
            }
        );
    }

    private function setupMutex(AggregateId $id): \Generator
    {
        $mutexKey = createAggregateMutexKey($id);

        if (false === \array_key_exists($mutexKey, $this->lockCollection))
        {
            $mutex = $this->mutexFactory->create($mutexKey);

            /** @var Lock $lock */
            $lock = yield $mutex->acquire();

            $this->lockCollection[$mutexKey] = $lock;
        }
    }

    private function releaseMutex(AggregateId $id): \Generator
    {
        $mutexKey = createAggregateMutexKey($id);

        if (\array_key_exists($mutexKey, $this->lockCollection))
        {
            /** @var Lock $lock */
            $lock = $this->lockCollection[$mutexKey];

            unset($this->lockCollection[$mutexKey]);

            yield $lock->release();
        }
    }
}
