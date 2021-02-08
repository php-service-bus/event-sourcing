<?php

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\EventSourcing\Snapshots\Store;

use function Amp\call;
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

    public function __construct(DatabaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function save(Snapshot $snapshot): Promise
    {
        return call(
            function () use ($snapshot): \Generator
            {
                $insertQuery = insertQuery(self::TABLE_NAME, [
                    'id'                 => $snapshot->aggregate->id()->toString(),
                    'aggregate_id_class' => \get_class($snapshot->aggregate->id()),
                    'aggregate_class'    => \get_class($snapshot->aggregate),
                    'version'            => $snapshot->aggregate->version(),
                    'payload'            => \base64_encode(\serialize($snapshot)),
                    'created_at'         => $snapshot->aggregate->getCreatedAt()->format('Y-m-d H:i:s.u'),
                ]);

                $compiledQuery = $insertQuery->compile();

                /** @psalm-suppress MixedArgumentTypeCoercion */
                yield $this->adapter->execute(
                    queryString: $compiledQuery->sql(),
                    parameters: $compiledQuery->params()
                );
            }
        );
    }

    public function load(AggregateId $id): Promise
    {
        return call(
            function () use ($id): \Generator
            {
                $storedSnapshot = null;

                $criteria = [
                    equalsCriteria('id', $id->toString()),
                    equalsCriteria('aggregate_id_class', \get_class($id)),
                ];

                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find(
                    queryExecutor: $this->adapter,
                    tableName: self::TABLE_NAME,
                    criteria: $criteria
                );

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

                if ($data !== null)
                {
                    $payload = $data['payload'];

                    if ($this->adapter instanceof BinaryDataDecoder)
                    {
                        $payload = $this->adapter->unescapeBinary($payload);
                    }

                    $snapshotContent = (string) \base64_decode($payload);

                    if ($snapshotContent !== '')
                    {
                        /** @var Snapshot $storedSnapshot */
                        $storedSnapshot = \unserialize($snapshotContent, ['allowed_classes' => true]);
                    }
                }

                return $storedSnapshot;
            }
        );
    }

    public function remove(AggregateId $id): Promise
    {
        return call(
            function () use ($id): \Generator
            {
                $criteria = [
                    equalsCriteria('id', $id->toString()),
                    equalsCriteria('aggregate_id_class', \get_class($id)),
                ];

                yield remove(
                    queryExecutor: $this->adapter,
                    tableName: self::TABLE_NAME,
                    criteria: $criteria
                );
            }
        );
    }
}
