<?php

namespace Tamara\Checkout\Block\Adminhtml\Whitelist;

class Import extends \Magento\Backend\Block\Widget\Form\Container
{
    public function _construct()
    {
        parent::_construct();
        $this->_blockGroup = 'Tamara_Checkout';
        $this->_controller = 'adminhtml_whitelist';
        $this->_mode = 'import';
        $this->buttonList->update('save', 'label', __('Import'));
        $this->buttonList->remove('delete');
        $this->buttonList->remove('reset');

    }
}
