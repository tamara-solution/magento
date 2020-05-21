<?php

namespace Tamara\Checkout\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Tamara\Checkout\Model\Order;

interface OrderRepositoryInterface
{
    /**
     * Save an order
     *
     * @param  OrderInterface $order
     * @return OrderInterface
     * @throws CouldNotSaveException
     */
    public function save(OrderInterface $order);

    /**
     * @param $id
     * @return Order
     */
    public function getTamaraOrderByOrderId($id);

    /**
     * @param $tamaraOrderId
     * @return Order
     */
    public function getTamaraOrderByTamaraOrderId($tamaraOrderId);
}