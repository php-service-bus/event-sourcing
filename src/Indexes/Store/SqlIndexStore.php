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

    /** @var DatabaseAdapter */
    private $adapter;

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function find(IndexKey $indexKey): Promise
    {
        return call(
            function() use ($indexKey): \Generator
            {
                $criteria = [
                    equalsCriteria('index_tag', $indexKey->indexName),
                    equalsCriteria('value_key', $indexKey->valueKey),
                ];

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet $resultSet */
                $resultSet = yield find($this->adapter, self::TABLE_NAME, $criteria);

                /** @var array<string, mixed>|null $result */
                $result = yield fetchOne($resultSet);

                if (true === \is_array($result))
                {
                    return new  IndexValue($result['value_data']);
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function add(IndexKey $indexKey, IndexValue $value): Promise
    {
        return call(
            function() use ($indexKey, $value): \Generator
            {
                /** @var \Latitude\QueryBuilder\Query\InsertQuery $insertQuery */
                $insertQuery = insertQuery(self::TABLE_NAME, [
                    'index_tag'  => $indexKey->indexName,
                    'value_key'  => $indexKey->valueKey,
                    'value_data' => $value->value,
                ]);

                $compiledQuery = $insertQuery->compile();

                /**
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                return $resultSet->affectedRows();
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete(IndexKey $indexKey): Promise
    {
        return call(
            function() use ($indexKey): \Generator
            {
                $criteria = [
                    equalsCriteria('index_tag', $indexKey->indexName),
                    equalsCriteria('value_key', $indexKey->valueKey),
                ];

                yield remove($this->adapter, self::TABLE_NAME, $criteria);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function update(IndexKey $indexKey, IndexValue $value): Promise
    {
        return call(
            function() use ($indexKey, $value): \Generator
            {
                $updateQuery = updateQuery(self::TABLE_NAME, ['value_data' => $value->value])
                    ->where(equalsCriteria('index_tag', $indexKey->indexName))
                    ->andWhere(equalsCriteria('value_key', $indexKey->valueKey));

                $compiledQuery = $updateQuery->compile();

                /**
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                return $resultSet->affectedRows();
            }
        );
    }
}
