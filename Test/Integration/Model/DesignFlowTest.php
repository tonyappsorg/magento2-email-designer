<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Integration\Model;

use Magento\Email\Model\ResourceModel\Template as TemplateResource;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\TemplateTypesInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\DesignRepository;
use Tonyapps\EmailDesigner\Model\Renderer;

/**
 * A design is a real core email template: saved, versioned, restored, rendered and sent through core.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class DesignFlowTest extends TestCase
{
    private const BASE = 'sales_email_order_template';
    private const HTML = '<html><body><h1>Order #{{var order.increment_id}}</h1>'
        . '<a href="{{store url="customer/account/"}}">Account</a> {{var store.frontend_name}}</body></html>';

    private DesignRepository $designs;

    protected function setUp(): void
    {
        $this->designs = Bootstrap::getObjectManager()->get(DesignRepository::class);
    }

    public function testSavingCreatesAnOrdinaryEmailTemplate(): void
    {
        $designId = $this->save(null, 'Order — autumn', 'Your order #{{var order.increment_id}}');

        $design = $this->designs->get($designId);
        $template = Bootstrap::getObjectManager()->create(TemplateFactory::class)->create();
        Bootstrap::getObjectManager()->get(TemplateResource::class)->load($template, (int)$design['template_id']);

        self::assertSame('Order — autumn', $template->getTemplateCode());
        self::assertSame(self::HTML, $template->getTemplateText());
        self::assertSame(self::BASE, $template->getOrigTemplateCode());
        self::assertEquals(TemplateTypesInterface::TYPE_HTML, $template->getTemplateType());
        self::assertArrayHasKey('var order.increment_id', json_decode((string)$template->getOrigTemplateVariables(), true));
        self::assertCount(1, $this->designs->getVersions($designId));
    }

    public function testSavingAgainUpdatesTheSameTemplateAndKeepsTenVersions(): void
    {
        $designId = $this->save(null, 'Order — winter', 'v0');
        $templateId = (int)$this->designs->get($designId)['template_id'];

        for ($i = 1; $i <= 11; $i++) {
            self::assertSame($designId, $this->save($designId, 'Order — winter ' . $i, 'v' . $i));
        }

        $design = $this->designs->get($designId);
        self::assertSame($templateId, (int)$design['template_id']);
        self::assertSame('Order — winter 11', $design['template_code']);
        $versions = $this->designs->getVersions($designId);
        self::assertCount(DesignRepository::KEEP_VERSIONS, $versions);
        self::assertSame('v11', $versions[0]['subject']);
    }

    public function testRestoringPutsAnOlderVersionBackAsTheNewest(): void
    {
        $designId = $this->save(null, 'Order — restore', 'first');
        $first = (int)$this->designs->getVersions($designId)[0]['version_id'];
        $this->designs->save($designId, 'Order — restore', self::BASE, 'second', '{"pages":[]}', '<mjml/>', '<p>second</p>', null);

        $this->designs->restore($designId, $first, null);

        $design = $this->designs->get($designId);
        self::assertSame('first', $design['template_subject']);
        self::assertSame(self::HTML, $design['template_text']);
        self::assertCount(3, $this->designs->getVersions($designId));
    }

    public function testATemplateNameCanOnlyBeUsedOnce(): void
    {
        $this->save(null, 'Order — taken', 'x');

        $this->expectException(\Exception::class);
        $this->save(null, 'Order — taken', 'y');
    }

    public function testDeletingTheTemplateInEmailTemplatesRemovesTheDesign(): void
    {
        $designId = $this->save(null, 'Order — cascade', 'x');
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->delete(
            $resource->getTableName('email_template'),
            ['template_id = ?' => (int)$this->designs->get($designId)['template_id']]
        );

        self::assertSame([], $this->designs->getList());
    }

    public function testTheListShowsWhichSettingsUseTheDesign(): void
    {
        $designId = $this->save(null, 'Order — in use', 'x');
        $templateId = (int)$this->designs->get($designId)['template_id'];
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resource->getConnection()->insert($resource->getTableName('core_config_data'), [
            'scope' => 'default', 'scope_id' => 0, 'path' => 'sales_email/order/template', 'value' => (string)$templateId,
        ]);

        $row = $this->designs->getList()[0];
        self::assertSame('sales_email/order/template', $row['used_in'][0]['path']);
    }

    public function testPreviewFillsTheEmailsVariablesWithSamples(): void
    {
        $rendered = Bootstrap::getObjectManager()->get(Renderer::class)
            ->render(self::HTML, 'Your order #{{var order.increment_id}}', self::BASE, 1);

        self::assertStringContainsString('Order #[', $rendered['html']);
        self::assertStringContainsString('customer/account/', $rendered['html']);
        self::assertStringNotContainsString('{{', $rendered['html']);
        self::assertStringStartsWith('Your order #[', $rendered['subject']);
    }

    public function testATestSendGoesOutThroughCore(): void
    {
        $designId = $this->save(null, 'Order — test send', 'Your order #{{var order.increment_id}}');
        $design = $this->designs->get($designId);

        $transport = Bootstrap::getObjectManager()->get(TransportBuilderMock::class);

        Bootstrap::getObjectManager()->create(Renderer::class, ['transportBuilder' => $transport])
            ->sendTest((int)$design['template_id'], self::BASE, 'designer@example.com', 1);

        $message = $transport->getSentMessage();
        self::assertNotNull($message);
        self::assertStringStartsWith('Your order #[', $message->getSubject());
        self::assertSame('designer@example.com', $message->getTo()[0]->getEmail());
    }

    private function save(?int $designId, string $name, string $subject): int
    {
        return $this->designs->save($designId, $name, self::BASE, $subject, '{"pages":[]}', '<mjml/>', self::HTML, null);
    }
}
