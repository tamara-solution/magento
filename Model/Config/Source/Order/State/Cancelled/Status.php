<?php

namespace Tamara\Checkout\Model\Config\Source\Order\State\Cancelled;


class Status extends \Magento\Sales\Model\Config\Source\Order\Status
{
    const STATUS_EXPIRED = 'tamara_expired';
    const STATUS_EXPIRED_LABEL = 'Expired';

    /**
     * @var string[]
     */
    protected $_stateStatuses = [
        \Magento\Sales\Model\Order::STATE_CANCELED
    ];
}
