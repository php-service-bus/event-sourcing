<?php

/**
 * Event Sourcing implementation module.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Module;

use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\EventSourcing\EventSourcingProvider;
use ServiceBus\EventSourcing\EventStream\EventStreamRepository;
use ServiceBus\EventSourcing\EventStream\Serializer\DefaultEventSerializer;
use ServiceBus\EventSourcing\EventStream\Serializer\EventSerializer;
use ServiceBus\EventSourcing\EventStream\Store\EventStreamStore;
use ServiceBus\EventSourcing\EventStream\Store\SqlEventStreamStore;
use ServiceBus\EventSourcing\Indexes\Store\IndexStore;
use ServiceBus\EventSourcing\Indexes\Store\SqlIndexStore;
use ServiceBus\EventSourcing\IndexProvider;
use ServiceBus\EventSourcing\Snapshots\Snapshotter;
use ServiceBus\EventSourcing\Snapshots\Store\SnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Store\SqlSnapshotStore;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotTrigger;
use ServiceBus\EventSourcing\Snapshots\Triggers\SnapshotVersionTrigger;
use ServiceBus\Mutex\InMemory\InMemoryMutexFactory;
use ServiceBus\Mutex\MutexFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @todo: custom store initialization
 */
final class EventSourcingModule implements ServiceBusModule
{
    /** @var string  */
    private $eventStoreServiceId;

    /** @var string  */
    private $snapshotStoreServiceId;

    /** @var string  */
    private $indexerStore;

    /** @var string|null  */
    private $databaseAdapterServiceId= null;

    /** @var string|null  */
    private $customEventSerializerServiceId= null;

    /** @var string|null  */
    private $customSnapshotStrategyServiceId = null;

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

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerBuilder $containerBuilder): void
    {
        /** Default configuration used */
        if (null !== $this->databaseAdapterServiceId)
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
        if (false === $containerBuilder->hasDefinition(MutexFactory::class))
        {
            $containerBuilder->addDefinitions([
                MutexFactory::class => new Definition(InMemoryMutexFactory::class),
            ]);
        }
    }

    private function registerIndexer(ContainerBuilder $containerBuilder): void
    {
        /** @psalm-suppress PossiblyNullArgument */
        $containerBuilder->addDefinitions([
            $this->indexerStore  => (new Definition(SqlIndexStore::class))->setArguments([new Reference((string) $this->databaseAdapterServiceId)]),
            IndexProvider::class => (new Definition(IndexProvider::class))->setArguments(
                [
                    new Reference($this->indexerStore),
                    new Reference(MutexFactory::class),
                ]
            ),
        ]);
    }

    private function registerEventSourcingProvider(ContainerBuilder $containerBuilder): void
    {
        $serializer = null;

        if (null !== $this->customEventSerializerServiceId)
        {
            $serializer = new Reference($this->customEventSerializerServiceId);
        }
        else
        {
            $containerBuilder->addDefinitions(
                [
                    EventSerializer::class => (new Definition(DefaultEventSerializer::class))->setArguments([
                        new Reference('service_bus.decoder.default_handler'),
                    ]),
                ]
            );

            $serializer = new Reference(EventSerializer::class);
        }

        $arguments = [
            new Reference($this->eventStoreServiceId),
            new Reference(Snapshotter::class),
            $serializer,
            new Reference('service_bus.logger'),
        ];

        $containerBuilder->addDefinitions([
            EventStreamRepository::class => (new Definition(EventStreamRepository::class))->setArguments($arguments),
            EventSourcingProvider::class => (new Definition(EventSourcingProvider::class))->setArguments(
                [
                    new Reference(EventStreamRepository::class),
                    new Reference(MutexFactory::class),
                ]
            ),
        ]);
    }

    private function registerSnapshotter(ContainerBuilder $containerBuilder): void
    {
        if (null === $this->customSnapshotStrategyServiceId)
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

    /**
     * @param string $eventStoreServiceId
     * @param string $snapshotStoreServiceId
     * @param string $indexerStore
     */
    private function __construct(string $eventStoreServiceId, string $snapshotStoreServiceId, string $indexerStore)
    {
        $this->eventStoreServiceId    = $eventStoreServiceId;
        $this->snapshotStoreServiceId = $snapshotStoreServiceId;
        $this->indexerStore           = $indexerStore;
    }
}
