<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Email\Model\Template\Config as EmailConfig;
use Magento\Email\Model\TemplateFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Tonyapps\EmailDesigner\Model\BlockPool;
use Tonyapps\EmailDesigner\Model\DesignRepository;
use Tonyapps\EmailDesigner\Model\PreviewPool;
use Tonyapps\EmailDesigner\Model\StarterLayout;
use Tonyapps\EmailDesigner\Model\TemplateVariables;
use Tonyapps\EmailDesigner\Model\Theme;

/** Everything the editor script needs, as one JSON config. */
class Editor extends Template
{
    /** Hidden field the media browser writes the chosen image into. */
    public const IMAGE_INPUT_ID = 'ta-designer-image';

    protected $_template = 'Tonyapps_EmailDesigner::editor.phtml';

    public function __construct(
        Context $context,
        private readonly DesignRepository $designs,
        private readonly TemplateVariables $templateVariables,
        private readonly EmailConfig $emailConfig,
        private readonly TemplateFactory $templateFactory,
        private readonly Session $authSession,
        private readonly Json $json,
        private readonly BlockPool $blockPool,
        private readonly Theme $theme,
        private readonly PreviewPool $previewPool,
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
            'starter' => StarterLayout::mjml(
                $logoUrl,
                $this->blockPool->getStarter($baseCode),
                $this->blockPool->getFor($baseCode)
            ),
            'logoUrl' => $logoUrl,
            'variables' => $this->templateVariables->getOptions($baseCode),
            'blocks' => $this->blockPool->getFor($baseCode),
            'adminEmail' => (string)$this->authSession->getUser()?->getEmail(),
            'images' => $this->getImagesConfig(),
            'theme' => $this->theme->get(),
            // Recent records to preview with, newest first (a list: JS would sort numeric object keys).
            'records' => array_map(
                static fn (string $id, string $label): array => ['id' => $id, 'label' => $label],
                array_map('strval', array_keys($records = $this->previewPool->getRecords($baseCode))),
                array_values($records)
            ),
            'defaultTheme' => Theme::DEFAULTS,
            'urls' => [
                'save' => $this->getUrl('*/*/save'),
                'preview' => $this->getUrl('*/*/preview'),
                'sendTest' => $this->getUrl('*/*/sendTest'),
                'restore' => $this->getUrl('*/*/restore'),
                'back' => $this->getUrl('*/*/index'),
            ],
        ]);
    }

    /**
     * Core's media browser, opened on pub/media/email (allowed as a media gallery folder in etc/config.xml, created
     * on first use). Null without the media gallery ACL. The browser returns a path; the editor prefixes the
     * storefront's media URL, as emails need absolute image URLs.
     *
     * @return array{browserUrl: string, mediaUrl: string, inputId: string}|null
     */
    private function getImagesConfig(): ?array
    {
        if (!$this->_authorization->isAllowed('Magento_Cms::media_gallery')) {
            return null;
        }
        return [
            'browserUrl' => $this->getUrl('cms/wysiwyg_images/index', [
                'target_element_id' => self::IMAGE_INPUT_ID,
                'type' => 'image',
                // Base64 of "email" in core's id encoding (Cms Images helper idEncode).
                'current_tree_path' => 'ZW1haWw-',
            ]),
            'mediaUrl' => (string)$this->_storeManager->getDefaultStoreView()?->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            'inputId' => self::IMAGE_INPUT_ID,
        ];
    }
}
