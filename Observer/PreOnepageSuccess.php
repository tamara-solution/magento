<?php

namespace Tamara\Checkout\Observer;

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

    public function __construct(
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
        ObjectManagerInterface $objectManager
    ) {
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->objectManager = $objectManager;
    }

    public function execute(Observer $observer)
    {
        $observer->getEvent()->getRequest()->setParams(['ajax' => 1]);

        $this->objectManager->get('TamaraCheckoutLogger')->debug(['before redirection' => 'marked as ajax call']);
    }
}
