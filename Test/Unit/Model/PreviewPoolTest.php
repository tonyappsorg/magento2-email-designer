<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\PreviewPool;

class PreviewPoolTest extends TestCase
{
    public function testAnEmailIsServedByTheProviderRegisteredForIt(): void
    {
        $cart = new class {
            public function getRecords(string $templateId, int $limit): array
            {
                return ['7' => "Cart #7 for $templateId, $limit"];
            }

            public function getVariables(string $templateId, string $recordId): array
            {
                return ['cart' => $recordId];
            }

            public function getStoreId(string $templateId, string $recordId): ?int
            {
                return 3;
            }
        };
        $pool = new PreviewPool(['cart' => ['templates' => ['cart_email'], 'provider' => $cart]]);

        self::assertSame(['7' => 'Cart #7 for cart_email, 5'], $pool->getRecords('cart_email', 5));
        self::assertSame(['cart' => '7'], $pool->getVariables('cart_email', '7'));
        self::assertSame(3, $pool->getStoreId('cart_email', '7', ['cart' => '7']));
        self::assertSame([], $pool->getRecords('other_email'));
        self::assertNull($pool->getVariables('other_email', '7'));
        self::assertNull($pool->getStoreId('other_email', '7', []));
    }

    public function testTheStoreInTheVariablesWinsAndGetStoreIdIsOptional(): void
    {
        $order = new class {
            public function getRecords(string $templateId, int $limit): array
            {
                return [];
            }

            public function getVariables(string $templateId, string $recordId): array
            {
                return [];
            }
        };
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $pool = new PreviewPool(['sales' => ['templates' => ['order_email'], 'provider' => $order]]);

        self::assertSame(2, $pool->getStoreId('order_email', '1', ['store' => $store]));
        self::assertNull($pool->getStoreId('order_email', '1', []));
    }
}
