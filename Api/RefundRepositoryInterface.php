<?php

namespace Tamara\Checkout\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Tamara\Checkout\Model\Refund;

interface RefundRepositoryInterface
{
    /**
     * Save an order
     *
     * @param  Refund $refund
     * @throws CouldNotSaveException
     */
    public function save(Refund $refund);

    /**
     * @param $orderId
     * @return Refund[]
     */
    public function getRefundsByOrderId($orderId);
}