<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Integration\Model;

use Magento\Config\Model\Config\Structure;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\DesignRepository;

/**
 * End to end: a design saved in the Email Designer and picked in a module's template setting is what that
 * module's cron sends. Skipped when the module is not installed.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class ModuleEmailsTest extends TestCase
{
    /**
     * @magentoDataFixture Magento/Sales/_files/shipment.php
     */
    public function testReviewReminderSendsThePickedDesign(): void
    {
        $this->requireModule('Tonyapps_ReviewReminder');
        $templateId = $this->pickDesign('tonyapps_reviewreminder/email/template', 'tonyapps_reviewreminder_email_template');
        $this->setStoreConfig('tonyapps_reviewreminder/general/enabled', '1');
        $this->setStoreConfig('tonyapps_reviewreminder/general/delay_days', '7');
        $this->sql()->update($this->table('sales_shipment'), ['created_at' => gmdate('Y-m-d H:i:s', strtotime('-10 days'))]);
        $this->sql()->update($this->table('sales_order_item'), ['qty_shipped' => 1]);

        $message = $this->send(\Tonyapps\ReviewReminder\Model\InvitationSender::class);

        self::assertSame('Designed 100000001', $message->getSubject());
        self::assertStringContainsString('DESIGNED BODY for 100000001', $message->getBodyText());
        self::assertSame($templateId, (int)$this->sql()->fetchOne(
            'SELECT template_id FROM ' . $this->table('email_template') . ' WHERE template_code = ?',
            ['Designed tonyapps_reviewreminder_email_template']
        ));
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     */
    public function testAbandonedCartSendsThePickedDesign(): void
    {
        $this->requireModule('Tonyapps_AbandonedCart');
        $this->pickDesign('tonyapps_abandonedcart/email/template', 'tonyapps_abandonedcart_email_template', 'storeName');
        $this->setStoreConfig('tonyapps_abandonedcart/general/enabled', '1');
        $this->sql()->update($this->table('quote'), [
            'is_active' => 1,
            'updated_at' => gmdate('Y-m-d H:i:s', strtotime('-2 hours')),
        ], ['reserved_order_id = ?' => 'test_order_1']);

        $message = $this->send(\Tonyapps\AbandonedCart\Model\ReminderSender::class);

        self::assertStringStartsWith('Designed ', $message->getSubject());
        self::assertStringContainsString('DESIGNED BODY for ', $message->getBodyText());
    }

    /** Saves a design for the email and picks it in the module's setting, after checking the setting renders. */
    private function pickDesign(string $path, string $baseCode, string $variable = 'orderIncrementId'): int
    {
        $options = Bootstrap::getObjectManager()->get(Structure::class)->getElementByConfigPath($path)->getOptions();
        self::assertSame($baseCode, $options[0]['value'], 'the setting offers the default template first');

        $designs = Bootstrap::getObjectManager()->get(DesignRepository::class);
        $designId = $designs->save(
            null,
            'Designed ' . $baseCode,
            $baseCode,
            'Designed {{var ' . $variable . '}}',
            '{"pages":[]}',
            '<mjml></mjml>',
            '<html><body><p>DESIGNED BODY for {{var ' . $variable . '}}</p></body></html>',
            null
        );
        $templateId = (int)$designs->get($designId)['template_id'];
        $this->setStoreConfig($path, (string)$templateId);
        return $templateId;
    }

    private function send(string $senderClass): \Magento\Framework\Mail\EmailMessageInterface
    {
        $transport = Bootstrap::getObjectManager()->get(TransportBuilderMock::class);
        Bootstrap::getObjectManager()->create($senderClass, ['transportBuilder' => $transport])->send();
        $message = $transport->getSentMessage();
        self::assertNotNull($message, 'the module sent an email');
        return $message;
    }

    private function requireModule(string $module): void
    {
        if (!Bootstrap::getObjectManager()->get(Manager::class)->isEnabled($module)) {
            self::markTestSkipped($module . ' is not installed.');
        }
    }

    private function setStoreConfig(string $path, string $value): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue($path, $value, ScopeInterface::SCOPE_STORE, 'default');
    }

    private function sql(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private function table(string $name): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName($name);
    }
}
