<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\Theme;

class ThemeTest extends TestCase
{
    public function testTheConfiguredThemeIsUsed(): void
    {
        self::assertSame([
            'primary' => '#aa0055',
            'background' => '#ffffff',
            'font' => 'Georgia, "Times New Roman", serif',
            'radius' => '0px',
            'footer' => 'Fashion & Co · {{var store_email}}',
        ], $this->theme([
            'primary' => '#AA0055',
            'background' => '#ffffff',
            'font' => 'Georgia, "Times New Roman", serif',
            'radius' => '0',
            'footer' => ' Fashion & Co · {{var store_email}} ',
        ])->get());
    }

    public function testInvalidOrMissingValuesFallBackToTheDefaultTheme(): void
    {
        self::assertSame(Theme::DEFAULTS, $this->theme([
            'primary' => 'red;background:url(x)',
            'background' => '#fff',
            'font' => 'Comic Sans MS',
            'radius' => '100',
            'footer' => '',
        ])->get());
    }

    /** @param array<string, string> $values */
    private function theme(array $values): Theme
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $values[substr($path, strlen('tonyapps_emaildesigner/theme/'))] ?? null
        );
        return new Theme($config);
    }
}
