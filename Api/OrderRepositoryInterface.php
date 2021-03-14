<?php

namespace Tamara\Checkout\Api;

use Magento\Framework\Exception\CouldNotSaveException;

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
     * @param int $magentoOrderId
     * @return OrderInterface
     */
    public function getTamaraOrderByOrderId($magentoOrderId);

    /**
     * @param $tamaraOrderId
     * @return OrderInterface
     */
    public function getTamaraOrderByTamaraOrderId($tamaraOrderId);
}