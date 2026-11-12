<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

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
}
