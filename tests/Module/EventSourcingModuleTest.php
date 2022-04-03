<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\EventSourcing\Tests\Module;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ServiceBus\EventSourcing\EventSourcingProvider;
use ServiceBus\EventSourcing\Module\EventSourcingModule;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 */
final class EventSourcingModuleTest extends TestCase
{
    /**
     * @test
     */
    public function createSqlStore(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            StorageConfiguration::class           => (new Definition(StorageConfiguration::class))->setArguments(['sqlite:///:memory:']),
            DatabaseAdapter::class                => (new Definition(DoctrineDBALAdapter::class))->setArguments([new Reference(StorageConfiguration::class)]),
            'service_bus.logger'                  => new Definition(NullLogger::class)
        ]);

        $module = EventSourcingModule::withSqlStorage(DatabaseAdapter::class);
        $module->boot($containerBuilder);

        $containerBuilder->getDefinition(EventSourcingProvider::class)->setPublic(true);

        $containerBuilder->compile();

        $containerBuilder->get(EventSourcingProvider::class);

        $this->assertTrue(true);
    }
}
