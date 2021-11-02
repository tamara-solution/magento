<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Tamara\Checkout\Model\Helper\PaymentHelper;


class PaymentMethodDisable implements ObserverInterface
{
    protected $tamaraCore;

    public function __construct(
        \Tamara\Checkout\Helper\Core $tamaraCore
    ) {
        $this->tamaraCore = $tamaraCore;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->tamaraCore->isAdminArea()) {
            if (PaymentHelper::isTamaraPayment($observer->getEvent()->getMethodInstance()->getCode())) {
                $checkResult = $observer->getEvent()->getResult();
                $checkResult->setData('is_available', false);
            }
        }
    }
}