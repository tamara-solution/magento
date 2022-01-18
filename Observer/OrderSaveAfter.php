<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface as MagentoOrderRepository;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;
use Tamara\Checkout\Model\Helper\ProductHelper;

class OrderSaveAfter extends AbstractObserver
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var BaseConfig
     */
    protected $config;

    /**
     * @var \Tamara\Checkout\Helper\Capture
     */
    protected $captureHelper;

    public function __construct(
        Logger $logger,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config,
        \Tamara\Checkout\Helper\Capture $captureHelper
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->captureHelper = $captureHelper;
    }


    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }
        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        $this->logger->debug(['Tamara - Start to order save after event']);

        if (!$this->config->getTriggerActions($order->getStoreId())) {
            $this->logger->debug(['Tamara - Turned off the trigger actions']);
            return;
        }

        if (!in_array(\Tamara\Checkout\Model\Config\Source\TriggerEvents\Options::CAPTURE_ORDER, $this->config->getTriggerEvents($order->getStoreId()))) {
            $this->logger->debug(['Tamara - Skip trigger refund event']);
            return;
        }

        $this->captureOrderWhenChangeStatus($order);

        $this->logger->debug(['Tamara - End to order save after event']);
    }


    /**
     * @param Order $order
     * @throws \Magento\Framework\Exception\IntegrationException
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    protected function captureOrderWhenChangeStatus(Order $order): void
    {
        if (empty($this->config->getOrderStatusShouldBeCaptured($order->getStoreId()))) {
            $this->logger->debug(['Tamara - Capture when order status change is not set, skip capture'], null,
                $this->config->enabledDebug($order->getStoreId()));
            return;
        }

        if ($order->getStatus() != $this->config->getOrderStatusShouldBeCaptured($order->getStoreId())) {
            return;
        }

        $this->captureHelper->captureOrder($order->getEntityId());
    }
}