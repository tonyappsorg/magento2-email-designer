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
        // The order email's own blocks.
        self::assertStringContainsString('"id":"sales_order_items"', $body);
        self::assertStringContainsString('sales_email_order_items', $body);
        self::assertStringNotContainsString('"id":"sales_invoice_items"', $body);
    }

    public function testTheEditorOpensTheMediaBrowserOnTheEmailFolder(): void
    {
        $this->dispatch('backend/tonyapps_emaildesigner/design/edit/base/sales_email_order_template/name/Autumn');

        $body = html_entity_decode($this->getResponse()->getBody());
        self::assertStringContainsString('cms\/wysiwyg_images\/index\/target_element_id\/ta-designer-image', $body);
        self::assertStringContainsString('current_tree_path\\/ZW1haWw-', $body);
        self::assertStringContainsString('id="ta-designer-image" data-force_static_path="1"', $body);
    }

    public function testCoresMediaBrowserOpensOnTheEmailFolder(): void
    {
        $media = Bootstrap::getObjectManager()->get(\Magento\Framework\Filesystem::class)
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

        $this->dispatch('backend/cms/wysiwyg_images/index/target_element_id/ta-designer-image/type/image/current_tree_path/ZW1haWw-');

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertStringContainsString('ta-designer-image', $this->getResponse()->getBody());
        self::assertTrue($media->isDirectory('email'), 'the email folder is created for the browser');
    }

    /** The editor turns this path into an absolute URL (designer.js addMediaGallery); never an admin directive URL. */
    public function testThePickedImageComesBackAsAPlainMediaPath(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(\Magento\Framework\Filesystem::class)
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->create('email');
        $images = $om->get(\Magento\Cms\Helper\Wysiwyg\Images::class);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue([
            'filename' => $images->idEncode('banner.png'),
            'node' => $images->idEncode('email'),
            'as_is' => 0,
            'force_static_path' => 1,
        ]);

        $this->dispatch('backend/cms/wysiwyg_images/onInsert');

        self::assertMatchesRegularExpression('#^/(pub/)?media/email/banner\.png$#', $this->getResponse()->getBody());
    }

    public function testCoresTemplatePageLinksADesignedTemplateToTheDesigner(): void
    {
        $repository = Bootstrap::getObjectManager()->get(DesignRepository::class);
        $designId = $repository->save(
            null, 'Spring order', 'sales_email_order_template', 'Order', '{"pages":[]}', '<mjml></mjml>', self::HTML, null
        );
        $templateId = (int)$repository->get($designId)['template_id'];

        $this->dispatch('backend/admin/email_template/edit/id/' . $templateId);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('Design Visually', $body);
        self::assertStringContainsString('tonyapps_emaildesigner/design/edit/id/' . $designId, $body);
    }

    public function testCoresTemplatePageHasNoDesignButtonForAHandWrittenTemplate(): void
    {
        $template = Bootstrap::getObjectManager()->create(\Magento\Email\Model\Template::class);
        $template->setTemplateCode('Hand written ' . uniqid())->setTemplateText('<p>Hi</p>')
            ->setTemplateSubject('Hi')->setTemplateType(2)->save();

        $this->dispatch('backend/admin/email_template/edit/id/' . $template->getId());

        self::assertStringContainsString('Delete Template', $this->getResponse()->getBody());
        self::assertStringNotContainsString('Design Visually', $this->getResponse()->getBody());
    }

    public function testTheBrandThemeIsSetInConfiguration(): void
    {
        $this->dispatch('backend/admin/system_config/edit/section/tonyapps_emaildesigner');
        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('Brand Theme', $body);
        self::assertMatchesRegularExpression('/<input id="tonyapps_emaildesigner_theme_primary"[^>]*type="color"/', $body);
        self::assertStringContainsString('Georgia', $body);
    }

    public function testTheEditorGetsTheBrandThemeAndTheDefaultItIsWrittenIn(): void
    {
        $config = Bootstrap::getObjectManager()->get(\Magento\Framework\App\Config\MutableScopeConfigInterface::class);
        $config->setValue('tonyapps_emaildesigner/theme/primary', '#aa0055');
        try {
            $this->dispatch('backend/tonyapps_emaildesigner/design/edit/base/sales_email_order_template/name/Autumn');
        } finally {
            $config->setValue('tonyapps_emaildesigner/theme/primary', null);
        }
        $editor = html_entity_decode($this->getResponse()->getBody());
        self::assertStringContainsString('"theme":{"primary":"#aa0055"', $editor);
        self::assertStringContainsString('"defaultTheme":{"primary":"#1979c3"', $editor);
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
