<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\StarterLayout;

class StarterLayoutTest extends TestCase
{
    public function testTheStarterUsesTheStoreLogoBlockAndStoreVariables(): void
    {
        $mjml = StarterLayout::mjml('https://shop.test/logo.png?a=1&b="2"');

        self::assertStringContainsString('src="https://shop.test/logo.png?a=1&amp;b=&quot;2&quot;"', $mjml);
        // The editor script swaps this block's src for {{var logo_url}} on save (js/directives.js LOGO_CLASS).
        self::assertStringContainsString('css-class="ta-store-logo"', $mjml);
        self::assertStringContainsString('{{var store.frontend_name}}', $mjml);
        self::assertNotFalse(simplexml_load_string($mjml), 'the starter is well-formed MJML');
    }
}
