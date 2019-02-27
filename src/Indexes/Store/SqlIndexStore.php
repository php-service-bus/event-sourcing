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
use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\selectQuery;
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
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(IndexKey $indexKey): \Generator
            {
                $selectQuery = selectQuery(self::TABLE_NAME)
                    ->where(equalsCriteria('index_tag', $indexKey->indexName))
                    ->andWhere(equalsCriteria('value_key', $indexKey->valueKey));

                $compiledQuery = $selectQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 *
                 * @var array<string, mixed>|null $result
                 */
                $result = yield fetchOne($resultSet);

                unset($selectQuery, $compiledQuery, $resultSet);

                if(null !== $result && true === \is_array($result))
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
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(IndexKey $indexKey, IndexValue $value): \Generator
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
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                $affectedRows = $resultSet->affectedRows();

                unset($insertQuery, $compiledQuery, $resultSet);

                return $affectedRows;
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
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(IndexKey $indexKey): \Generator
            {
                $deleteQuery = deleteQuery(self::TABLE_NAME)
                    ->where(equalsCriteria('index_tag', $indexKey->indexName))
                    ->andWhere(equalsCriteria('value_key', $indexKey->valueKey));

                $compiledQuery = $deleteQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                unset($deleteQuery, $compiledQuery, $resultSet);
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
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(IndexKey $indexKey, IndexValue $value): \Generator
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
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                $affectedRows = $resultSet->affectedRows();

                unset($updateQuery, $compiledQuery, $resultSet);

                return $affectedRows;
            },
            $indexKey,
            $value
        );
    }
}
