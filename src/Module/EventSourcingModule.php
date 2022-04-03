<?php

/**
 * Event Sourcing implementation module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\EventSourcing\Module;

use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\EventSourcing\EventSourcingProvider;
use ServiceBus\EventSourcing\EventStream\EventStreamRepository;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\SqlEventStreamStore;
use ServiceBus\EventSourcing\Indexes\Store\IndexStore;
use ServiceBus\EventSourcing\Indexes\Store\SqlIndexStore;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;
use ServiceBus\EventSourcing\Snapshots\Store\SnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Store\SqlSnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotTrigger;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotVersionTrigger;
use ServiceBus\MessageSerializer\ObjectDenormalizer;
use ServiceBus\MessageSerializer\ObjectSerializer;
use ServiceBus\MessageSerializer\Symfony\SymfonyJsonObjectSerializer;
use ServiceBus\MessageSerializer\Symfony\SymfonyObjectDenormalizer;
use ServiceBus\Mutex\InMemory\InMemoryMutexService;
use ServiceBus\Mutex\MutexService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @todo: custom store initialization
 */
final class EventSourcingModule implements ServiceBusModule
{
    /**
     * @var string
     */
    private $eventStoreServiceId;

    /**
     * @var string
     */
    private $snapshotStoreServiceId;

    /**
     * @var string
     */
    private $indexerStore;

    /**
     * @var string|null
     */
    private $databaseAdapterServiceId;

    /**
     * @var string|null
     */
    private $customEventSerializerServiceId;

    /**
     * @var string|null
     */
    private $customSnapshotStrategyServiceId;

    public static function withSqlStorage(string $databaseAdapterServiceId): self
    {
        $self = new self(
            EventStreamStore::class,
            SnapshotStore::class,
            IndexStore::class
        );

        $self->databaseAdapterServiceId = $databaseAdapterServiceId;

        return $self;
    }

    public function withCustomEventSerializer(string $eventSerializerServiceId): self
    {
        $this->customEventSerializerServiceId = $eventSerializerServiceId;

        return $this;
    }

    public function withCustomSnapshotStrategy(string $snapshotStrategyServiceId): self
    {
        $this->customSnapshotStrategyServiceId = $snapshotStrategyServiceId;

        return $this;
    }

    public function boot(ContainerBuilder $containerBuilder): void
    {
        /** Default configuration used */
        if ($this->databaseAdapterServiceId !== null)
        {
            $storeArguments = [new Reference($this->databaseAdapterServiceId)];

            $containerBuilder->addDefinitions([
                $this->eventStoreServiceId    => (new Definition(SqlEventStreamStore::class))->setArguments($storeArguments),
                $this->snapshotStoreServiceId => (new Definition(SqlSnapshotStore::class))->setArguments($storeArguments),
                $this->indexerStore           => (new Definition(SqlIndexStore::class))->setArguments($storeArguments),
            ]);
        }

        $this->registerMutexFactory($containerBuilder);
        $this->registerSnapshotter($containerBuilder);
        $this->registerEventSourcingProvider($containerBuilder);
        $this->registerIndexer($containerBuilder);
    }

    private function registerMutexFactory(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(MutexService::class) === false)
        {
            $containerBuilder->addDefinitions([
                MutexService::class => new Definition(InMemoryMutexService::class),
            ]);
        }
    }

    private function registerIndexer(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            $this->indexerStore => (new Definition(SqlIndexStore::class))->setArguments([new Reference((string) $this->databaseAdapterServiceId)]),
        ]);
    }

    private function registerEventSourcingProvider(ContainerBuilder $containerBuilder): void
    {
        if ($this->customEventSerializerServiceId !== null)
        {
            $serializer = new Reference($this->customEventSerializerServiceId);
        }
        else
        {
            $containerBuilder->addDefinitions(
                [
                    ObjectSerializer::class => new Definition(SymfonyJsonObjectSerializer::class)
                ]
            );

            $serializer = new Reference(ObjectSerializer::class);
        }

        if ($containerBuilder->hasDefinition(ObjectDenormalizer::class) === false)
        {
            $containerBuilder->addDefinitions(
                [ObjectDenormalizer::class => new Definition(SymfonyObjectDenormalizer::class)]
            );
        }

        $arguments = [
            new Reference($this->eventStoreServiceId),
            new Reference(Snapshotter::class),
            new Reference(ObjectDenormalizer::class),
            $serializer,
            new Reference('service_bus.logger'),
        ];

        $containerBuilder->addDefinitions([
            EventStreamRepository::class => (new Definition(EventStreamRepository::class))->setArguments($arguments),
            EventSourcingProvider::class => (new Definition(EventSourcingProvider::class))->setArguments(
                [
                    new Reference(EventStreamRepository::class),
                    new Reference(MutexService::class),
                ]
            ),
        ]);
    }

    private function registerSnapshotter(ContainerBuilder $containerBuilder): void
    {
        if ($this->customSnapshotStrategyServiceId === null)
        {
            $containerBuilder->addDefinitions([
                SnapshotTrigger::class => new Definition(SnapshotVersionTrigger::class, [30]),
            ]);

            $this->customSnapshotStrategyServiceId = SnapshotTrigger::class;
        }

        $arguments = [
            new Reference($this->snapshotStoreServiceId),
            new Reference($this->customSnapshotStrategyServiceId),
            new Reference('service_bus.logger'),
        ];

        $containerBuilder->addDefinitions([
            Snapshotter::class => (new Definition(Snapshotter::class))->setArguments($arguments),
        ]);
    }

    private function __construct(string $eventStoreServiceId, string $snapshotStoreServiceId, string $indexerStore)
    {
        $this->eventStoreServiceId    = $eventStoreServiceId;
        $this->snapshotStoreServiceId = $snapshotStoreServiceId;
        $this->indexerStore           = $indexerStore;
    }
}
