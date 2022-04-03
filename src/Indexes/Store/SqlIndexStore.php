<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Indexes\Store;

use Amp\Promise;
use ServiceBus\EventSourcing\Indexes\IndexKey;
use ServiceBus\EventSourcing\Indexes\IndexValue;
use ServiceBus\Storage\Common\DatabaseAdapter;
use function Amp\call;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\remove;
use function ServiceBus\Storage\Sql\updateQuery;

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

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function find(IndexKey $indexKey): Promise
    {
        return call(
            function () use ($indexKey): \Generator
            {
                $criteria = [
                    equalsCriteria('index_tag', $indexKey->indexName),
                    equalsCriteria('value_key', $indexKey->valueKey),
                ];

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet $resultSet */
                $resultSet = yield find(
                    queryExecutor: $this->adapter,
                    tableName: self::TABLE_NAME,
                    criteria: $criteria
                );

                /** @var array<string, int|float|string>|null $result */
                $result = yield fetchOne($resultSet);

                if (\is_array($result))
                {
                    return new IndexValue($result['value_data']);
                }

                return null;
            }
        );
    }

    public function add(IndexKey $indexKey, IndexValue $value): Promise
    {
        return call(
            function () use ($indexKey, $value): \Generator
            {
                $insertQuery = insertQuery(self::TABLE_NAME, [
                    'index_tag'  => $indexKey->indexName,
                    'value_key'  => $indexKey->valueKey,
                    'value_data' => $value->value,
                ]);

                $compiledQuery = $insertQuery->compile();

                /** @psalm-suppress MixedArgumentTypeCoercion */
                $resultSet = yield $this->adapter->execute(
                    queryString: $compiledQuery->sql(),
                    parameters: $compiledQuery->params()
                );

                return $resultSet->affectedRows();
            }
        );
    }

    public function delete(IndexKey $indexKey): Promise
    {
        return call(
            function () use ($indexKey): \Generator
            {
                $criteria = [
                    equalsCriteria('index_tag', $indexKey->indexName),
                    equalsCriteria('value_key', $indexKey->valueKey),
                ];

                yield remove(
                    queryExecutor: $this->adapter,
                    tableName: self::TABLE_NAME,
                    criteria: $criteria
                );
            }
        );
    }

    public function update(IndexKey $indexKey, IndexValue $value): Promise
    {
        return call(
            function () use ($indexKey, $value): \Generator
            {
                $updateQuery = updateQuery(self::TABLE_NAME, ['value_data' => $value->value])
                    ->where(equalsCriteria('index_tag', $indexKey->indexName))
                    ->andWhere(equalsCriteria('value_key', $indexKey->valueKey));

                $compiledQuery = $updateQuery->compile();

                /** @psalm-suppress MixedArgumentTypeCoercion */
                $resultSet = yield $this->adapter->execute(
                    queryString: $compiledQuery->sql(),
                    parameters: $compiledQuery->params()
                );

                return $resultSet->affectedRows();
            }
        );
    }
}
