<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Indexes\Store;

use function Amp\call;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\remove;
use function ServiceBus\Storage\Sql\updateQuery;
use Amp\Promise;
use ServiceBus\EventSourcing\Indexes\IndexKey;
use ServiceBus\EventSourcing\Indexes\IndexValue;
use ServiceBus\Storage\Common\DatabaseAdapter;

/**
 *
 */
final class SqlIndexStore implements IndexStore
{
    private const TABLE_NAME = 'event_sourcing_indexes';

    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @param DatabaseAdapter $adapter
     */
    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * {@inheritdoc}
     */
    public function find(IndexKey $indexKey): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(IndexKey $indexKey) use ($adapter): \Generator
            {
                $criteria = [
                    equalsCriteria('index_tag', $indexKey->indexName),
                    equalsCriteria('value_key', $indexKey->valueKey),
                ];

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet $resultSet */
                $resultSet = yield find($adapter, self::TABLE_NAME, $criteria);

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 *
                 * @var array<string, mixed>|null $result
                 */
                $result = yield fetchOne($resultSet);

                if (null !== $result && true === \is_array($result))
                {
                    return IndexValue::create($result['value_data']);
                }
            },
            $indexKey
        );
    }

    /**
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * {@inheritdoc}
     */
    public function add(IndexKey $indexKey, IndexValue $value): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(IndexKey $indexKey, IndexValue $value) use ($adapter): \Generator
            {
                /** @var \Latitude\QueryBuilder\Query\InsertQuery $insertQuery */
                $insertQuery = insertQuery(self::TABLE_NAME, [
                    'index_tag'  => $indexKey->indexName,
                    'value_key'  => $indexKey->valueKey,
                    'value_data' => $value->value,
                ]);

                $compiledQuery = $insertQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                return $resultSet->affectedRows();
            },
            $indexKey,
            $value
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete(IndexKey $indexKey): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(IndexKey $indexKey) use ($adapter): \Generator
            {
                $criteria = [
                    equalsCriteria('index_tag', $indexKey->indexName),
                    equalsCriteria('value_key', $indexKey->valueKey),
                ];

                yield remove($adapter, self::TABLE_NAME, $criteria);
            },
            $indexKey
        );
    }

    /**
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * {@inheritdoc}
     */
    public function update(IndexKey $indexKey, IndexValue $value): Promise
    {
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(IndexKey $indexKey, IndexValue $value) use ($adapter): \Generator
            {
                $updateQuery = updateQuery(self::TABLE_NAME, ['value_data' => $value->value])
                    ->where(equalsCriteria('index_tag', $indexKey->indexName))
                    ->andWhere(equalsCriteria('value_key', $indexKey->valueKey));

                $compiledQuery = $updateQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                return $resultSet->affectedRows();
            },
            $indexKey,
            $value
        );
    }
}
