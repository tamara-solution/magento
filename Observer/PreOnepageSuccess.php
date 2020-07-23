<?php

namespace Tamara\Checkout\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;

class PreOnepageSuccess extends AbstractObserver
{
    private $storeManager;
    private $orderRepository;
    private $objectManager;
    private $checkoutSession;

    public function __construct(
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
        ObjectManagerInterface $objectManager,
        Session $checkoutSession
    ) {
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->objectManager = $objectManager;
        $this->checkoutSession = $checkoutSession;

    }

    public function execute(Observer $observer)
    {
        $logger = $this->objectManager->get('TamaraCheckoutLogger');
        $logger->debug(['Start to preonepage success']);
        $orderId = $this->checkoutSession->getLastOrderId();
        $objectManager = ObjectManager::getInstance();

        /** @var Order $order */
        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);

        if (!$order instanceof Order) {
            return;
        }

        $logger->debug(['orderId' => $orderId]);
        $logger->debug(['payment' => $order->getPayment()->getMethod()]);

        if (!$this->isTamaraPayment($order->getPayment()->getMethod())) {
            return;
        }

        $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId((int)$order->getId());
        $redirect = $objectManager->get('\Magento\Framework\App\Response\Http');
        $redirect->setRedirect($tamaraOrder->getRedirectUrl());

        $observer->getEvent()->getRequest()->setParams(['ajax' => 1]);

        $logger->debug(['before redirection' => 'marked as ajax call']);
    }
}
