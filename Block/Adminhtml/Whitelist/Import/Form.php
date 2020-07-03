<?php

namespace Tamara\Checkout\Block\Adminhtml\Whitelist\Import;

use \Magento\Backend\Block\Widget\Form\Generic as FormGeneric;


class Form extends FormGeneric
{
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create(['data' => array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/processImport'),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        )]);

        $fieldset = $form->addFieldset('profile_fieldset', array());

        $fieldset->addField('filecsv', 'file', array(
            'label' => __('Import File'),
            'title' => __('Import File'),
            'name' => 'filecsv',
            'required' => true,
        ));

        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
