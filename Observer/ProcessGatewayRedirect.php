<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Magento\Framework\App\ObjectManager;

class ProcessGatewayRedirect extends AbstractObserver
{
    private $storeManager;
    private $orderRepository;

    public function __construct(
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
    }

    public function execute(Observer $observer)
    {
        $orderId = $observer->getEvent()->getOrderIds();
        $objectManager = ObjectManager::getInstance();

        /** @var Order $order */
        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId[0]);

        if (!$this->isTamaraPayment($order->getPayment()->getMethod())) {
            return;
        }

        $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId((int)$order->getId());
        $redirect = $objectManager->get('\Magento\Framework\App\Response\Http');
        $redirect->setRedirect($tamaraOrder->getRedirectUrl());
    }
}