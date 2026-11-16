<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Integration\Model;

use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\PreviewPool;
use Tonyapps\EmailDesigner\Model\Renderer;

/**
 * The preview with a real order shows that order's email, built by core's own sender, and never sends or changes
 * anything, also with asynchronous sending on or the email type switched off.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class RealDataPreviewTest extends TestCase
{
    private const HTML = '<p>Order {{var order.increment_id}} for {{var order_data.customer_name}}</p>'
        . '{{layout handle="sales_email_order_items" order_id=$order_id area="frontend"}}'
        . '<p>Invoice {{var invoice.increment_id}}</p>';

    private TransportBuilderMock $transportBuilder;
    private MutableScopeConfigInterface $config;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->transportBuilder = $om->get(TransportBuilderMock::class);
        $this->config = $om->get(MutableScopeConfigInterface::class);
        // Stripe's plugin on the email identity containers loads the admin "create order" session quote in its
        // constructor; a quote id left there by an earlier test points at a deleted quote.
        $om->get(\Magento\Backend\Model\Session\Quote::class)->clearStorage();
    }

    protected function tearDown(): void
    {
        $this->config->setValue('sales_email/general/async_sending', 0);
        $this->config->setValue('sales_email/order/enabled', 1, 'store', 'default');
    }

    /** @magentoDataFixture Magento/Sales/_files/order.php */
    public function testTheOrderListOffersTheRealOrder(): void
    {
        $records = Bootstrap::getObjectManager()->get(PreviewPool::class)->getRecords('sales_email_order_guest_template');

        self::assertContains('#100000001', array_map(static fn (string $label): string => strtok($label, ' '), $records));
        // The fixture order is a guest order, so the customer email's list leaves it out.
        $customer = Bootstrap::getObjectManager()->get(PreviewPool::class)->getRecords('sales_email_order_template');
        self::assertNotContains('#100000001', array_map(static fn (string $label): string => strtok($label, ' '), $customer));
    }

    /** @magentoDataFixture Magento/Sales/_files/order.php */
    public function testThePreviewShowsTheRealOrderAndChangesNothing(): void
    {
        $order = $this->order();
        $before = $this->emailFlags($order);

        foreach ([[0, 1], [1, 1], [0, 0]] as [$async, $enabled]) {
            $this->config->setValue('sales_email/general/async_sending', $async);
            $this->config->setValue('sales_email/order/enabled', $enabled, 'store', 'default');

            $html = $this->renderer()->render(self::HTML, 'S', 'sales_email_order_guest_template', 1, (string)$order->getId())['html'];

            self::assertStringContainsString('Order 100000001', $html);
            self::assertStringNotContainsString('shown with real data', $html, 'the items table is the real one');
            self::assertStringContainsString('Simple Product', $html, 'order items rendered');
            self::assertNull($this->transportBuilder->getSentMessage(), 'nothing is sent');
            self::assertSame($before, $this->emailFlags($order), 'nothing is saved');
        }
    }

    /** @magentoDataFixture Magento/Sales/_files/invoice.php */
    public function testAnInvoicePreviewUsesCoresInvoiceVariables(): void
    {
        $invoiceId = (string)$this->order()->getInvoiceCollection()->getFirstItem()->getId();
        $variables = Bootstrap::getObjectManager()->get(PreviewPool::class)
            ->getVariables('sales_email_invoice_guest_template', $invoiceId);

        self::assertSame($invoiceId, (string)$variables['invoice_id']);
        self::assertSame('100000001', $variables['order']->getIncrementId());
        self::assertArrayHasKey('payment_html', $variables);
        self::assertNull($this->transportBuilder->getSentMessage());
    }

    public function testSampleDataStaysTheDefault(): void
    {
        $html = $this->renderer()->render(self::HTML, 'S', 'sales_email_order_template', 1)['html'];

        self::assertStringContainsString('shown with real data in the email', $html);
    }

    private function renderer(): Renderer
    {
        return Bootstrap::getObjectManager()->get(Renderer::class);
    }

    private function order(): Order
    {
        return Bootstrap::getObjectManager()->create(Order::class)->loadByIncrementId('100000001');
    }

    /** @return array<string, mixed> straight from the database */
    private function emailFlags(Order $order): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        return $resource->getConnection()->fetchRow(
            $resource->getConnection()->select()
                ->from($resource->getTableName('sales_order'), ['send_email', 'email_sent'])
                ->where('entity_id = ?', $order->getId())
        );
    }
}
