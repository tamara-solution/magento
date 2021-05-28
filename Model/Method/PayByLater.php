<?php

namespace Tamara\Checkout\Model\Method;

class PayByLater extends \Tamara\Checkout\Model\Method\Checkout
{
    public function isActive($storeId = null)
    {
        $isActive = parent::isActive($storeId);
        return $isActive && isset($this->tamaraHelper->getPaymentTypesOfStore($storeId)[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]);
    }
}