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
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\remove;
use Amp\Promise;
use ServiceBus\EventSourcing\AggregateId;
use ServiceBus\EventSourcing\Snapshots\Snapshot;
use ServiceBus\Storage\Common\BinaryDataDecoder;
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
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(Snapshot $snapshot) use ($adapter): \Generator
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

                /** @psalm-suppress MixedTypeCoercion Invalid params() docblock */
                yield $adapter->execute($compiledQuery->sql(), $compiledQuery->params());
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
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id) use ($adapter): \Generator
            {
                $storedSnapshot = null;

                $criteria = [
                    equalsCriteria('id', $id->toString()),
                    equalsCriteria('aggregate_id_class', \get_class($id)),
                ];

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find($adapter, self::TABLE_NAME, $criteria);

                /**
                 * @psalm-var      array{
                 *   id: string,
                 *   aggregate_id_class: string,
                 *   aggregate_class: string,
                 *   version: int,
                 *   payload:string,
                 *   created_at: string
                 * }|null $data
                 *
                 * @var array<string, string>|null $data
                 */
                $data = yield fetchOne($resultSet);

                if (true === \is_array($data) && 0 !== \count($data))
                {
                    $payload = $data['payload'];

                    if ($adapter instanceof BinaryDataDecoder)
                    {
                        $payload = $adapter->unescapeBinary($payload);
                    }

                    $snapshotContent = (string) \base64_decode($payload);

                    if ('' !== $snapshotContent)
                    {
                        /** @var Snapshot $storedSnapshot */
                        $storedSnapshot = \unserialize($snapshotContent, ['allowed_classes' => true]);
                    }
                }

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
        $adapter = $this->adapter;

        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            static function(AggregateId $id) use ($adapter): \Generator
            {
                $criteria = [
                    equalsCriteria('id', $id->toString()),
                    equalsCriteria('aggregate_id_class', \get_class($id)),
                ];

                yield remove($adapter, self::TABLE_NAME, $criteria);
            },
            $id
        );
    }
}
