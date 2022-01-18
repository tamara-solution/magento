<?php

declare(strict_types=1);

namespace Tamara\Checkout\Block\Adminhtml\System\Config\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ExtensionVersion extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \Tamara\Checkout\Helper\Core
     */
    protected $tamaraCoreHelper;

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Tamara\Checkout\Helper\Core $tamaraCoreHelper,
        array $data = []
    ) {
        $this->tamaraCoreHelper = $tamaraCoreHelper;
        parent::__construct($context, $data);
    }

    protected function _renderValue(AbstractElement $element)
    {
        $version = $this->getVersion();
        if ($version == \Tamara\Checkout\Helper\Core::UNKNOWN) {
            $version = "Cannot get plugin version from composer";
        }
        return '<td class="value">Plugin version: ' . $version . ', PHP SDK version: ' . $this->tamaraCoreHelper->getPHPSDKVersion() .'</td>';
    }

    public function getVersion()
    {
        return $this->tamaraCoreHelper->getPluginVersion();
    }

    protected function _renderInheritCheckbox(AbstractElement $element)
    {
        return '<td class="use-default"></td>';
    }
}