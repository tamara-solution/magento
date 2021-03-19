<?php

namespace Tamara\Checkout\Model\Config\Source\Order\State\PendingPayment;


class Status extends \Magento\Sales\Model\Config\Source\Order\Status
{

    /**
     * @var string[]
     */
    protected $_stateStatuses = [
        \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
    ];
}
