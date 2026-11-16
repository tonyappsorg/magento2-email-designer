<?php
declare(strict_types=1);

namespace Tonyapps\EmailDesigner\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/** The browser's own colour picker for a #rrggbb setting. */
class Color extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setType('color');
        return parent::_getElementHtml($element);
    }
}
