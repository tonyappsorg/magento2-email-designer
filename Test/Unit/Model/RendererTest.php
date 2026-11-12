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
            new BlockPool()
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
        $renderer = new Renderer($factory, $this->createMock(TemplateVariables::class), $config, $this->createMock(TransportBuilder::class), new BlockPool());

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
        $pool = new BlockPool(['items' => ['label' => 'Order items', 'templates' => [], 'directive' => $items]]);
        $config = $this->createMock(EmailConfig::class);
        $config->method('getTemplateArea')->willReturn('frontend');
        $renderer = new Renderer($factory, $this->createMock(TemplateVariables::class), $config,
            $this->createMock(TransportBuilder::class), $pool);

        $html = $renderer->render('<p>{{var order.increment_id}}</p>' . $items . '{{layout handle="other"}}', 'S', 'x', 1)['html'];

        self::assertStringContainsString('<p>{{var order.increment_id}}</p>', $html, 'other directives are left to the filter');
        self::assertStringContainsString('Order items (shown with real data in the email)', $html);
        self::assertStringContainsString('Magento block (shown with real data in the email)', $html);
        self::assertStringNotContainsString('{{layout', $html);
    }
}
