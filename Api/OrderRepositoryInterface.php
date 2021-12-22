<?php

namespace Tamara\Checkout\Api;

use Magento\Framework\Exception\CouldNotSaveException;

interface OrderRepositoryInterface
{
    /**
     * Save an order
     *
     * @param  \Tamara\Checkout\Api\OrderInterface $order
     * @return \Tamara\Checkout\Api\OrderInterface
     * @throws CouldNotSaveException
     */
    public function save(OrderInterface $order);

    /**
     * @param int $magentoOrderId
     * @return \Tamara\Checkout\Api\OrderInterface
     */
    public function getTamaraOrderByOrderId($magentoOrderId);

    /**
     * @param $tamaraOrderId
     * @return \Tamara\Checkout\Api\OrderInterface
     */
    public function getTamaraOrderByTamaraOrderId($tamaraOrderId);
}