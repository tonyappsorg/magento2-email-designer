<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Email\Model\Template\Config as EmailConfig;
use Tonyapps\EmailDesigner\Model\DesignRepository;

/** The design list and the "New design" form. */
class Designs extends Template
{
    protected $_template = 'Tonyapps_EmailDesigner::designs.phtml';

    public function __construct(
        Context $context,
        private readonly DesignRepository $designs,
        private readonly EmailConfig $emailConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function getDesigns(): array
    {
        return $this->designs->getList();
    }

    /** @return array<string, array<string, string>> module => [email code => label], theme overrides left out */
    public function getEmailOptions(): array
    {
        $groups = [];
        foreach ($this->emailConfig->getAvailableTemplates() as $option) {
            if (str_contains((string)$option['value'], '/')) {
                continue;
            }
            $groups[(string)$option['group']][(string)$option['value']] = (string)$option['label'];
        }
        ksort($groups);
        foreach ($groups as &$options) {
            asort($options);
        }
        return $groups;
    }

    public function getEmailLabel(string $code): string
    {
        try {
            return (string)$this->emailConfig->getTemplateLabel($code);
        } catch (\UnexpectedValueException) {
            return $code;
        }
    }

    /** @param array<string, mixed> $setting a core_config_data row */
    public function describeSetting(array $setting): string
    {
        $scope = $setting['scope'] === 'default' ? (string)__('Default') : $setting['scope'] . ' #' . $setting['scope_id'];
        return $setting['path'] . ' (' . $scope . ')';
    }

    public function getEditUrl(?int $designId = null): string
    {
        return $this->getUrl('*/*/edit', $designId ? ['id' => $designId] : []);
    }
}
