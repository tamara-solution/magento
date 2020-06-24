<?php

declare(strict_types=1);

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

class OrderPaymentPlaceEnd extends AbstractObserver
{
    private const STATUS_PENDING = 'pending';

    public function execute(Observer $observer): void
    {
        /** @var Order\Payment $payment */
        $payment = $observer->getEvent()->getPayment();

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        $payment->getOrder()->setState(Order::STATE_NEW)->setStatus(self::STATUS_PENDING);
    }
}
