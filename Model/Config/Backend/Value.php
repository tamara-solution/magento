<?php

namespace Tamara\Checkout\Model\Config\Backend;

class Value extends \Magento\Framework\App\Config\Value
{
    /**
     * @return \Magento\Framework\App\Config\Value
     */
    public function beforeSave()
    {
        if (!$this->isValueChanged()) {
            return parent::beforeSave();
        }
        $this->setValue(trim($this->getValue()));
        return parent::beforeSave();
    }
}
