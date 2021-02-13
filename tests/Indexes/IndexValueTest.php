<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Event Sourcing implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\EventSourcing\Tests\Indexes;

use PHPUnit\Framework\TestCase;
use ServiceBus\EventSourcing\Indexes\IndexValue;

/**
 *
 */
final class IndexValueTest extends TestCase
{
    /**
     * @test
     */
    public function successCreate(): void
    {
        new IndexValue(0);
    }
}
