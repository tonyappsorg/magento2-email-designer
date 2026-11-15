<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Plugin;

use Magento\Email\Block\Adminhtml\Template\Edit;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use Tonyapps\EmailDesigner\Model\DesignRepository;

/**
 * *Design visually* on core's Email Templates edit page, for templates written by the designer. Added before core
 * builds the page, because core pushes its buttons to the toolbar while preparing the layout.
 */
class DesignVisuallyButton
{
    public function __construct(
        private readonly DesignRepository $designs,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url
    ) {
    }

    public function beforeSetLayout(Edit $subject, LayoutInterface $layout): null
    {
        $templateId = (int)$subject->getRequest()->getParam('id');
        if (!$templateId || !$this->authorization->isAllowed('Tonyapps_EmailDesigner::designs')) {
            return null;
        }
        $designId = $this->designs->findIdByTemplate($templateId);
        if ($designId) {
            $subject->addButton('tonyapps_design_visually', [
                'label' => __('Design Visually'),
                'onclick' => sprintf("setLocation('%s')", $this->url->getUrl('tonyapps_emaildesigner/design/edit', ['id' => $designId])),
            ], 0, 5);
        }
        return null;
    }
}
