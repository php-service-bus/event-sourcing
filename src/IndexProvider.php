<?php

declare(strict_types=1);

namespace ServiceBus\EventSourcing;

use Amp\Promise;
use ServiceBus\EventSourcing\Exceptions\IndexOperationFailed;
use ServiceBus\EventSourcing\Indexes\IndexKey;
use ServiceBus\EventSourcing\Indexes\IndexValue;
use ServiceBus\EventSourcing\Indexes\Store\IndexStore;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use function Amp\call;

final class IndexProvider
{
    /**
     * @var IndexStore
     */
    private $store;

    public function __construct(IndexStore $store)
    {
        $this->store = $store;
    }

    /**
     * Receive index value.
     *
     * @return Promise<\ServiceBus\EventSourcing\Indexes\IndexValue|null>
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\IndexOperationFailed
     */
    public function get(IndexKey $indexKey): Promise
    {
        return call(
            function () use ($indexKey): \Generator
            {
                try
                {
                    return yield $this->store->find($indexKey);
                }
                catch (\Throwable $throwable)
                {
                    throw IndexOperationFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * Is there a value in the index.
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\IndexOperationFailed
     */
    public function has(IndexKey $indexKey): Promise
    {
        return call(
            function () use ($indexKey): \Generator
            {
                try
                {
                    /** @var IndexValue|null $value */
                    $value = yield $this->store->find($indexKey);

                    return $value !== null;
                }
                catch (\Throwable $throwable)
                {
                    throw IndexOperationFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * Add a value to the index. If false, then the value already exists.
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\IndexOperationFailed
     */
    public function add(IndexKey $indexKey, IndexValue $value): Promise
    {
        return call(
            function () use ($indexKey, $value): \Generator
            {
                try
                {
                    /** @var int $affectedRows */
                    $affectedRows = yield $this->store->add($indexKey, $value);

                    return $affectedRows !== 0;
                }
                catch (UniqueConstraintViolationCheckFailed)
                {
                    return false;
                }
                catch (\Throwable $throwable)
                {
                    throw IndexOperationFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * Remove value from index.
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\IndexOperationFailed
     */
    public function remove(IndexKey $indexKey): Promise
    {
        return call(
            function () use ($indexKey): \Generator
            {
                try
                {
                    yield $this->store->delete($indexKey);
                }
                catch (\Throwable $throwable)
                {
                    throw IndexOperationFailed::fromThrowable($throwable);
                }
            }
        );
    }

    /**
     * Update value in index.
     *
     * @throws \ServiceBus\EventSourcing\Exceptions\IndexOperationFailed
     */
    public function update(IndexKey $indexKey, IndexValue $value): Promise
    {
        return call(
            function () use ($indexKey, $value): \Generator
            {
                try
                {
                    /** @var int $affectedRows */
                    $affectedRows = yield $this->store->update($indexKey, $value);

                    return $affectedRows !== 0;
                }
                catch (\Throwable $throwable)
                {
                    throw IndexOperationFailed::fromThrowable($throwable);
                }
            }
        );
    }
}
