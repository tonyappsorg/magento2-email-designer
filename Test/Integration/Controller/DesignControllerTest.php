<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Integration\Controller;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Tonyapps\EmailDesigner\Model\DesignRepository;

/**
 * The admin pages render through the real layout, blocks and templates, and the AJAX actions answer JSON.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class DesignControllerTest extends AbstractBackendController
{
    protected $resource = 'Tonyapps_EmailDesigner::designs';
    protected $uri = 'backend/tonyapps_emaildesigner/design/index';

    private const HTML = '<html><body>{{var order.increment_id}}</body></html>';

    public function testTheListOffersEveryEmailForANewDesign(): void
    {
        $this->dispatch('backend/tonyapps_emaildesigner/design/index');

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('New design', $body);
        self::assertStringContainsString('value="sales_email_order_template"', $body);
        self::assertStringContainsString('No designs yet.', $body);
    }

    public function testANewDesignOpensTheEditorWithTheEmailsVariables(): void
    {
        $this->dispatch('backend/tonyapps_emaildesigner/design/edit/base/sales_email_order_template/name/Autumn');

        $body = html_entity_decode($this->getResponse()->getBody());
        self::assertStringContainsString('Tonyapps_EmailDesigner/js/designer', $body);
        self::assertStringContainsString('"base":"sales_email_order_template"', $body);
        self::assertStringContainsString('{{var order.increment_id}}', $body);
        self::assertStringContainsString('ta-store-logo', $body);
    }

    public function testTheEditorNeedsAKnownEmailAndAName(): void
    {
        $this->dispatch('backend/tonyapps_emaildesigner/design/edit/base/nope/name/Autumn');
        $this->assertRedirect(self::stringContains('design/index'));
        $this->assertSessionMessages(self::containsEqual('Choose the email the design is for.'));
    }

    public function testSaveAnswersWithTheNewDesign(): void
    {
        $saved = $this->post('save', [
            'name' => 'Autumn order',
            'base' => 'sales_email_order_template',
            'subject' => 'Order {{var order.increment_id}}',
            'project' => '{"pages":[]}',
            'mjml' => '<mjml></mjml>',
            'html' => self::HTML,
        ]);

        self::assertTrue($saved['success'], $saved['message'] ?? '');
        self::assertCount(1, $saved['versions']);
        self::assertStringContainsString('design/edit/id/' . $saved['id'], $saved['editUrl']);
    }

    public function testASavedDesignOpensWithItsName(): void
    {
        $designId = Bootstrap::getObjectManager()->get(DesignRepository::class)->save(
            null, 'Winter order', 'sales_email_order_template', 'Order', '{"pages":[]}', '<mjml></mjml>', self::HTML, null
        );

        $this->dispatch('backend/tonyapps_emaildesigner/design/edit/id/' . $designId);

        self::assertStringContainsString('"name":"Winter order"', html_entity_decode($this->getResponse()->getBody()));
    }

    public function testPreviewRendersWithSampleData(): void
    {
        $preview = $this->post('preview', ['html' => self::HTML, 'subject' => 'Order', 'base' => 'sales_email_order_template']);

        self::assertTrue($preview['success'], $preview['message'] ?? '');
        self::assertStringContainsString('[', $preview['html']);
    }

    public function testAnIncompleteSaveAnswersWithAMessage(): void
    {
        $result = $this->post('save', ['name' => '', 'base' => 'sales_email_order_template', 'project' => '{}', 'html' => '']);

        self::assertFalse($result['success']);
        self::assertNotEmpty($result['message']);
    }

    /** @return array<string, mixed> */
    private function post(string $action, array $params): array
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($params);
        $this->dispatch('backend/tonyapps_emaildesigner/design/' . $action);
        return json_decode($this->getResponse()->getBody(), true);
    }
}
