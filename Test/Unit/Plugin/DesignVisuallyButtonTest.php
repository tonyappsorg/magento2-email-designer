<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Test\Unit\Plugin;

use Magento\Email\Block\Adminhtml\Template\Edit;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\EmailDesigner\Model\DesignRepository;
use Tonyapps\EmailDesigner\Plugin\DesignVisuallyButton;

class DesignVisuallyButtonTest extends TestCase
{
    public function testADesignedTemplateGetsAButtonToItsDesign(): void
    {
        $designs = $this->createMock(DesignRepository::class);
        $designs->method('findIdByTemplate')->with(12)->willReturn(4);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('tonyapps_emaildesigner/design/edit', ['id' => 4])->willReturn('https://admin/design/4');
        $block = $this->block(12);
        $block->expects(self::once())->method('addButton')->with(
            'tonyapps_design_visually',
            self::callback(static fn (array $data): bool => (string)$data['label'] === 'Design Visually'
                && $data['onclick'] === "setLocation('https://admin/design/4')")
        );

        (new DesignVisuallyButton($designs, $this->allowed(true), $url))->beforeSetLayout($block, $this->createMock(LayoutInterface::class));
    }

    public function testAHandWrittenTemplateGetsNoButton(): void
    {
        $designs = $this->createMock(DesignRepository::class);
        $designs->method('findIdByTemplate')->willReturn(null);
        $block = $this->block(12);
        $block->expects(self::never())->method('addButton');

        (new DesignVisuallyButton($designs, $this->allowed(true), $this->createMock(UrlInterface::class)))
            ->beforeSetLayout($block, $this->createMock(LayoutInterface::class));
    }

    public function testWithoutTheDesignerAclOrOnANewTemplateNothingIsLookedUp(): void
    {
        $designs = $this->createMock(DesignRepository::class);
        $designs->expects(self::never())->method('findIdByTemplate');
        foreach ([[12, false], [0, true]] as [$templateId, $allowed]) {
            $block = $this->block($templateId);
            $block->expects(self::never())->method('addButton');
            (new DesignVisuallyButton($designs, $this->allowed($allowed), $this->createMock(UrlInterface::class)))
                ->beforeSetLayout($block, $this->createMock(LayoutInterface::class));
        }
    }

    private function block(int $templateId): Edit
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('id')->willReturn((string)$templateId);
        $block = $this->createMock(Edit::class);
        $block->method('getRequest')->willReturn($request);
        return $block;
    }

    private function allowed(bool $allowed): AuthorizationInterface
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($allowed);
        return $authorization;
    }
}
