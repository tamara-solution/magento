<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;

abstract class AbstractObserver implements ObserverInterface
{
    public const ALLOWED_PAYMENTS = [
        PayLaterConfig::PAYMENT_TYPE_CODE
    ];

    abstract public function execute(Observer $observer);

    protected function isTamaraPayment($method)
    {
        return in_array($method, self::ALLOWED_PAYMENTS, true);
    }
}