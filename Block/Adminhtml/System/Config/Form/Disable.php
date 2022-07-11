<?php

namespace Tamara\Checkout\Block\Adminhtml\System\Config\Form;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Disable extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setData('disabled', 1);
        $element->setData('readonly', 1);
        return $element->getElementHtml();
    }
}