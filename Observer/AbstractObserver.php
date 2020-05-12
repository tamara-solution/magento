<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;
use Tamara\Checkout\Model\Helper\PaymentHelper;

abstract class AbstractObserver implements ObserverInterface
{
    abstract public function execute(Observer $observer);

    protected function isTamaraPayment($method)
    {
        return PaymentHelper::isTamaraPayment($method);
    }
}