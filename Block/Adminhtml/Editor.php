<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Tonyapps\EmailDesigner\Model\DesignRepository;
use Tonyapps\EmailDesigner\Model\StarterLayout;
use Tonyapps\EmailDesigner\Model\TemplateVariables;

/** Everything the editor script needs, as one JSON config. */
class Editor extends Template
{
    protected $_template = 'Tonyapps_EmailDesigner::editor.phtml';

    public function __construct(
        Context $context,
        private readonly DesignRepository $designs,
        private readonly TemplateVariables $templateVariables,
        private readonly EmailConfig $emailConfig,
        private readonly TemplateFactory $templateFactory,
        private readonly Session $authSession,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        $designId = (int)$this->getRequest()->getParam('id');
        $logoUrl = (string)$this->templateFactory->create()->getDefaultEmailLogo();
        if ($designId) {
            $design = $this->designs->get($designId);
            $baseCode = (string)$design['base_template_code'];
            $config = [
                'id' => $designId,
                'name' => (string)$design['template_code'],
                'subject' => (string)$design['template_subject'],
                'project' => json_decode((string)$design['project_data'], true),
                'versions' => $this->designs->getVersions($designId),
            ];
        } else {
            $baseCode = (string)$this->getRequest()->getParam('base');
            $config = [
                'id' => null,
                'name' => trim((string)$this->getRequest()->getParam('name')),
                'subject' => $this->templateVariables->getDefaultSubject($baseCode),
                'project' => null,
                'versions' => [],
            ];
        }

        return (string)$this->json->serialize($config + [
            'base' => $baseCode,
            'baseLabel' => (string)$this->emailConfig->getTemplateLabel($baseCode),
            'starter' => StarterLayout::mjml($logoUrl),
            'logoUrl' => $logoUrl,
            'variables' => $this->templateVariables->getOptions($baseCode),
            'adminEmail' => (string)$this->authSession->getUser()?->getEmail(),
            'urls' => [
                'save' => $this->getUrl('*/*/save'),
                'preview' => $this->getUrl('*/*/preview'),
                'sendTest' => $this->getUrl('*/*/sendTest'),
                'restore' => $this->getUrl('*/*/restore'),
                'back' => $this->getUrl('*/*/index'),
            ],
        ]);
    }
}
