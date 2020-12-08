<?php

namespace Tamara\Checkout\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Widget\Button as MagentoButton;

class UpdateMinMax extends Field
{
    protected $_template = 'Tamara_Checkout::system/config/update_min_max.phtml';

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getCustomUrl()
    {
        return $this->getUrl('config/system/payments');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            MagentoButton::class
        )->setData(
            [
                'class' => 'update_config',
                'label' => __('Update Config'),
            ]
        );
        return $button->toHtml();
    }
}