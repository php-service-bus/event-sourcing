<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Snapshots\Store;

use function Amp\call;
use function ServiceBus\Common\datetimeToString;
use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\selectQuery;
use Amp\Promise;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\Snapshots\Snapshot;
use ServiceBus\Storage\Common\DatabaseAdapter;

/**
 *
 */
final class SqlSnapshotStore implements SnapshotStore
{
    private const TABLE_NAME = 'event_store_snapshots';

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
     * {@inheritdoc}
     */
    public function save(Snapshot $snapshot): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Snapshot $snapshot): \Generator
            {
                $insertQuery = insertQuery(self::TABLE_NAME, [
                    'id'                 => $snapshot->aggregate->id(),
                    'aggregate_id_class' => \get_class($snapshot->aggregate->id()),
                    'aggregate_class'    => \get_class($snapshot->aggregate),
                    'version'            => $snapshot->aggregate->version(),
                    'payload'            => \base64_encode(\serialize($snapshot)),
                    'created_at'         => datetimeToString($snapshot->aggregate->getCreatedAt()),
                ]);

                $compiledQuery = $insertQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                unset($insertQuery, $compiledQuery, $resultSet);
            },
            $snapshot
        );
    }

    /**
     * @psalm-suppress MixedTypeCoercion Incorrect resolving the value of the promise
     *
     * {@inheritdoc}
     */
    public function load(AggregateId $id): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(AggregateId $id): \Generator
            {
                $storedSnapshot = null;

                $selectQuery = selectQuery(self::TABLE_NAME)
                    ->where(equalsCriteria('id', $id))
                    ->andWhere(equalsCriteria('aggregate_id_class', \get_class($id)));

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
                 * @var array<string, string>|null $data
                 */
                $data = yield fetchOne($resultSet);

                if (true === \is_array($data) && 0 !== \count($data))
                {
                    /** @var Snapshot $storedSnapshot */
                    $storedSnapshot = \unserialize(
                        \base64_decode($this->adapter->unescapeBinary($data['payload'])),
                        ['allowed_classes' => true]
                    );
                }

                unset($resultSet, $data, $selectQuery, $compiledQuery);

                return $storedSnapshot;
            },
            $id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function remove(AggregateId $id): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(AggregateId $id): \Generator
            {
                $deleteQuery = deleteQuery(self::TABLE_NAME)
                    ->where(equalsCriteria('id', $id))
                    ->andWhere(equalsCriteria('aggregate_id_class', \get_class($id)));

                $compiledQuery = $deleteQuery->compile();

                /**
                 * @psalm-suppress TooManyTemplateParams Wrong Promise template
                 * @psalm-suppress MixedTypeCoercion Invalid params() docblock
                 *
                 * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                 */
                $resultSet = yield $this->adapter->execute($compiledQuery->sql(), $compiledQuery->params());

                unset($resultSet, $compiledQuery, $deleteQuery);
            },
            $id
        );
    }
}
