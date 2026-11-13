<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\BlockPool;

class BlockPoolTest extends TestCase
{
    public function testAnEmailGetsOnlyItsOwnBlocksInRegistrationOrder(): void
    {
        $pool = new BlockPool([
            'items' => ['label' => 'Order items', 'templates' => ['order', 'order_guest'], 'directive' => '{{layout handle="items"}}'],
            'cart' => ['label' => 'Cart', 'templates' => ['cart'], 'directive' => '{{var cart}}'],
            'address' => ['label' => 'Address', 'templates' => ['order', 'invoice'], 'directive' => '{{var address|raw}}'],
        ]);

        self::assertSame([
            ['id' => 'items', 'label' => 'Order items', 'directive' => '{{layout handle="items"}}'],
            ['id' => 'address', 'label' => 'Address', 'directive' => '{{var address|raw}}'],
        ], $pool->getFor('order'));
        self::assertSame([], $pool->getFor('unknown'));
        self::assertSame('Cart', $pool->getLabel('{{var cart}}'));
        self::assertNull($pool->getLabel('{{var other}}'));
    }

    public function testAStarterIsReadFromTheRegisteringModule(): void
    {
        $registrar = $this->createMock(ComponentRegistrar::class);
        $registrar->method('getPath')->with(ComponentRegistrar::MODULE, 'Tonyapps_EmailDesigner')
            ->willReturn(dirname(__DIR__, 3));
        $pool = new BlockPool([], [
            'cart' => 'Tonyapps_EmailDesigner::registration.php',
            'missing' => 'Tonyapps_EmailDesigner::nope.mjml',
            'malformed' => 'registration.php',
        ], $registrar);

        self::assertStringContainsString('Tonyapps_EmailDesigner', (string)$pool->getStarter('cart'));
        self::assertNull($pool->getStarter('missing'));
        self::assertNull($pool->getStarter('malformed'));
        self::assertNull($pool->getStarter('unregistered'));
    }
}
