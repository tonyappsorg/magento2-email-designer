<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Model;

use Magento\Email\Model\Template;
use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\BlockPool;
use Tonyapps\EmailDesigner\Model\PreviewPool;
use Tonyapps\EmailDesigner\Model\Renderer;
use Tonyapps\EmailDesigner\Model\TemplateVariables;

class RendererTest extends TestCase
{
    public function testATestNeedsAValidAddress(): void
    {
        $transport = $this->createMock(TransportBuilder::class);
        $transport->expects(self::never())->method('setTemplateIdentifier');
        $renderer = new Renderer(
            $this->createMock(TemplateFactory::class),
            $this->createMock(TemplateVariables::class),
            $this->createMock(EmailConfig::class),
            $transport,
            new BlockPool(new \Magento\Framework\Component\ComponentRegistrar()),
            new PreviewPool()
        );

        $this->expectException(LocalizedException::class);
        $renderer->sendTest(5, 'sales_email_order_template', 'not-an-email', 1);
    }

    public function testRenderingFailuresBecomeAReadableError(): void
    {
        $template = $this->getMockBuilder(Template::class)->disableOriginalConstructor()
            ->onlyMethods(['setDesignConfig', 'getProcessedTemplateSubject', 'getProcessedTemplate'])->getMock();
        $template->method('setDesignConfig')->willReturnSelf();
        $template->method('getProcessedTemplateSubject')->willReturn('Subject');
        $template->method('getProcessedTemplate')->willThrowException(new \LogicException('Unknown directive'));
        $factory = $this->createMock(TemplateFactory::class);
        $factory->method('create')->willReturn($template);
        $config = $this->createMock(EmailConfig::class);
        $config->method('getTemplateArea')->willReturn('frontend');
        $renderer = new Renderer($factory, $this->createMock(TemplateVariables::class), $config, $this->createMock(TransportBuilder::class), new BlockPool(new \Magento\Framework\Component\ComponentRegistrar()), new PreviewPool());

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown directive');
        $renderer->render('<p>{{oops}}</p>', 'Subject', 'sales_email_order_template', 1);
    }

    public function testThePreviewShowsALabelledBoxForLayoutBlocks(): void
    {
        $template = $this->getMockBuilder(Template::class)->disableOriginalConstructor()
            ->onlyMethods(['setDesignConfig', 'getProcessedTemplateSubject', 'getProcessedTemplate'])->getMock();
        $template->method('setDesignConfig')->willReturnSelf();
        $template->method('getProcessedTemplate')->willReturnCallback(fn () => $template->getTemplateText());
        $factory = $this->createMock(TemplateFactory::class);
        $factory->method('create')->willReturn($template);
        $items = '{{layout handle="sales_email_order_items" order_id=$order_id area="frontend"}}';
        $pool = new BlockPool(new \Magento\Framework\Component\ComponentRegistrar(), ['items' => ['label' => 'Order items', 'templates' => [], 'directive' => $items]]);
        $config = $this->createMock(EmailConfig::class);
        $config->method('getTemplateArea')->willReturn('frontend');
        $renderer = new Renderer($factory, $this->createMock(TemplateVariables::class), $config,
            $this->createMock(TransportBuilder::class), $pool, new PreviewPool());

        $html = $renderer->render('<p>{{var order.increment_id}}</p>' . $items . '{{layout handle="other"}}', 'S', 'x', 1)['html'];

        self::assertStringContainsString('<p>{{var order.increment_id}}</p>', $html, 'other directives are left to the filter');
        self::assertStringContainsString('Order items (shown with real data in the email)', $html);
        self::assertStringContainsString('Magento block (shown with real data in the email)', $html);
        self::assertStringNotContainsString('{{layout', $html);
    }

    public function testARealRecordUsesItsVariablesAndStoreAndKeepsLayoutBlocks(): void
    {
        $template = $this->getMockBuilder(Template::class)->disableOriginalConstructor()
            ->onlyMethods(['setDesignConfig', 'getProcessedTemplateSubject', 'getProcessedTemplate'])->getMock();
        $template->expects(self::once())->method('setDesignConfig')->with(['area' => 'frontend', 'store' => 4])->willReturnSelf();
        $template->expects(self::once())->method('getProcessedTemplate')->with(['cart' => 'real'])
            ->willReturnCallback(fn () => $template->getTemplateText());
        $factory = $this->createMock(TemplateFactory::class);
        $factory->method('create')->willReturn($template);
        $config = $this->createMock(EmailConfig::class);
        $config->method('getTemplateArea')->willReturn('frontend');
        $samples = $this->createMock(TemplateVariables::class);
        $samples->expects(self::never())->method('getSampleData');
        $provider = new class {
            public function getRecords(string $templateId, int $limit): array
            {
                return [];
            }

            public function getVariables(string $templateId, string $recordId): array
            {
                return ['cart' => 'real'];
            }

            public function getStoreId(string $templateId, string $recordId): ?int
            {
                return 4;
            }
        };
        $renderer = new Renderer($factory, $samples, $config, $this->createMock(TransportBuilder::class), new BlockPool(new \Magento\Framework\Component\ComponentRegistrar()),
            new PreviewPool(['cart' => ['templates' => ['cart_email'], 'provider' => $provider]]));

        $html = $renderer->render('{{layout handle="items"}}', 'S', 'cart_email', 1, '9')['html'];

        self::assertSame('{{layout handle="items"}}', $html, 'real data renders the real block');
    }

    public function testARecordForAnEmailWithoutProviderIsRefused(): void
    {
        $renderer = new Renderer($this->createMock(TemplateFactory::class), $this->createMock(TemplateVariables::class),
            $this->createMock(EmailConfig::class), $this->createMock(TransportBuilder::class), new BlockPool(new \Magento\Framework\Component\ComponentRegistrar()), new PreviewPool());

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $renderer->render('<p></p>', 'S', 'unknown', 1, '9');
    }
}
