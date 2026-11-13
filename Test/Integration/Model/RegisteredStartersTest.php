<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Integration\Model;

use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\BlockPool;
use Tonyapps\EmailDesigner\Model\Renderer;

/**
 * Every Tonyapps email of an enabled module has a starter design whose blocks are registered for that email, and
 * the starter with its blocks renders through core's template filter (strict mode) with sample data.
 *
 * @magentoAppArea adminhtml
 */
class RegisteredStartersTest extends TestCase
{
    public function testEveryTonyappsEmailHasAStarterThatUsesItsOwnBlocksAndRenders(): void
    {
        $config = Bootstrap::getObjectManager()->get(EmailConfig::class);
        $checked = 0;
        foreach ($config->getAvailableTemplates() as $template) {
            // Theme overrides are listed too, as "id/Vendor_theme"; only the module's own template counts.
            if (str_starts_with((string)$template['group'], 'Tonyapps_') && !str_contains($template['value'], '/')) {
                $this->assertStarter($template['value']);
                $checked++;
            }
        }
        self::assertGreaterThanOrEqual(17, $checked, 'all Tonyapps emails in this install');
    }

    private function assertStarter(string $templateId): void
    {
        $pool = Bootstrap::getObjectManager()->get(BlockPool::class);
        $starter = $pool->getStarter($templateId);
        self::assertNotNull($starter, $templateId . ': no starter design registered');
        self::assertNotFalse(simplexml_load_string('<mj-column>' . $starter . '</mj-column>'), $templateId . ': well-formed MJML');

        $blocks = array_column($pool->getFor($templateId), 'directive', 'id');
        preg_match_all('/<mj-raw ta-block="([^"]+)"><\/mj-raw>/', $starter, $used);
        self::assertSame([], array_diff($used[1], array_keys($blocks)), $templateId . ': blocks not registered for this email');

        // Blocks as the editor writes them, around the starter's own text (MJML tags mean nothing to the filter).
        $html = (string)preg_replace_callback(
            '/<mj-raw ta-block="([^"]+)"><\/mj-raw>/',
            static fn (array $m): string => '<table><tr><td>' . $blocks[$m[1]] . '</td></tr></table>',
            $starter
        );
        $rendered = Bootstrap::getObjectManager()->get(Renderer::class)->render($html, 'S', $templateId, 1)['html'];

        self::assertStringNotContainsString('{{', $rendered, $templateId);
        self::assertStringNotContainsString('Error filtering template', $rendered, $templateId);
    }
}
