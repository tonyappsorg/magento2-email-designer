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
        $pool = new BlockPool(new \Magento\Framework\Component\ComponentRegistrar(), [
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
        $pool = new BlockPool($registrar, [], [
            'cart' => 'Tonyapps_EmailDesigner::registration.php',
            'missing' => 'Tonyapps_EmailDesigner::nope.mjml',
            'malformed' => 'registration.php',
        ]);

        self::assertStringContainsString('Tonyapps_EmailDesigner', (string)$pool->getStarter('cart'));
        self::assertNull($pool->getStarter('missing'));
        self::assertNull($pool->getStarter('malformed'));
        self::assertNull($pool->getStarter('unregistered'));
    }

    /**
     * setup:di:compile writes constructor defaults into generated/metadata with var_export(); an object default
     * (`= new X()`) becomes X::__set_state() there and every request fatals in production mode.
     */
    public function testNoConstructorInTheModuleHasAnObjectDefault(): void
    {
        $root = dirname(__DIR__, 3);
        $files = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)), '/\.php$/');
        foreach ($files as $file) {
            $relative = substr((string)$file, strlen($root) + 1);
            if (str_starts_with($relative, 'Test/') || $relative === 'registration.php') {
                continue;
            }
            $class = 'Tonyapps\\EmailDesigner\\' . str_replace('/', '\\', substr($relative, 0, -4));
            $constructor = class_exists($class) ? (new \ReflectionClass($class))->getConstructor() : null;
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                if ($parameter->isDefaultValueAvailable()) {
                    self::assertIsNotObject($parameter->getDefaultValue(), $class . '::$' . $parameter->getName());
                }
            }
        }
    }
}
