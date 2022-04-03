<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing;

use ServiceBus\EventSourcing\EventStream\RevertModeType;
use ServiceBus\EventSourcing\Exceptions\AggregateNotFound;
use ServiceBus\Mutex\MutexService;
use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\EventSourcing\EventStream\EventStreamRepository;
use ServiceBus\EventSourcing\Exceptions\DuplicateAggregate;
use ServiceBus\EventSourcing\Exceptions\LoadAggregateFailed;
use ServiceBus\EventSourcing\Exceptions\RevertAggregateVersionFailed;
use ServiceBus\EventSourcing\Exceptions\SaveAggregateFailed;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use function Amp\call;

final class EventSourcingProvider
{
    /**
     * @var EventStreamRepository
     */
    private $repository;

    /**
     * @var MutexService
     */
    private $mutexService;

    public function __construct(EventStreamRepository $repository, MutexService $mutexService)
    {
        $this->repository   = $repository;
        $this->mutexService = $mutexService;
    }

    /**
     * Load aggregate.
     *
     * @psalm-param callable(Aggregate):\Generator $onLoaded
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\AggregateNotFound
     * @throws \ServiceBus\EventSourcing\Exceptions\LoadAggregateFailed
     */
    public function load(AggregateId $id, ServiceBusContext $context, callable $onLoaded): Promise
    {
        return call(
            function () use ($id, $context, $onLoaded): \Generator
            {
                try
                {
                    yield $this->mutexService->withLock(
                        id: createAggregateMutexKey($id),
                        code: function () use ($id, $context, $onLoaded): \Generator
                        {
                            /** @var Aggregate|null $aggregate */
                            $aggregate = yield $this->repository->load($id);

                            if ($aggregate !== null)
                            {
                                yield call($onLoaded, $aggregate);

                                $events = yield $this->repository->update($aggregate);

                                if (\count($events) !== 0)
                                {
                                    yield $context->deliveryBulk($events);
                                }

                                return;
                            }

                            throw new AggregateNotFound($id);
                        }
                    );
                }
                catch (\Throwable $throwable)
                {
                    throw LoadAggregateFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * Save a new aggregate.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\SaveAggregateFailed
     * @throws \ServiceBus\EventSourcing\Exceptions\DuplicateAggregate
     */
    public function store(Aggregate $aggregate, ServiceBusContext $context): Promise
    {
        return call(
            function () use ($aggregate, $context): \Generator
            {
                try
                {
                    yield $this->mutexService->withLock(
                        id: createAggregateMutexKey($aggregate->id()),
                        code: function () use ($aggregate, $context): \Generator
                        {
                            $events = yield $this->repository->save($aggregate);

                            /** @var object[] $events */

                            if (\count($events) !== 0)
                            {
                                yield $context->deliveryBulk($events);
                            }
                        }
                    );
                }
                catch (UniqueConstraintViolationCheckFailed)
                {
                    throw DuplicateAggregate::create($aggregate->id());
                }
                catch (\Throwable $throwable)
                {
                    throw SaveAggregateFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * Revert aggregate to specified version.
     *
     * @psalm-param callable(Aggregate):\Generator $onReverted
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\RevertAggregateVersionFailed
     */
    public function revert(
        AggregateId    $id,
        ServiceBusContext $context,
        int            $toVersion,
        callable       $onReverted,
        RevertModeType $mode = RevertModeType::SOFT_DELETE
    ): Promise {
        return call(
            function () use ($id, $context, $toVersion, $mode, $onReverted): \Generator
            {
                try
                {
                    yield $this->mutexService->withLock(
                        id: createAggregateMutexKey($id),
                        code: function () use ($id, $context, $toVersion, $mode, $onReverted): \Generator
                        {
                            /** @var Aggregate $aggregate */
                            $aggregate = yield $this->repository->revert($id, $toVersion, $mode);

                            yield call($onReverted, $aggregate);

                            $events = yield $this->repository->update($aggregate);

                            /** @var object[] $events */

                            if (\count($events) !== 0)
                            {
                                yield $context->deliveryBulk($events);
                            }
                        }
                    );
                }
                catch (\Throwable $throwable)
                {
                    throw RevertAggregateVersionFailed::fromThrowable($throwable);
                }
            }
        );
    }
}
