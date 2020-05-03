<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;
use Tamara\Checkout\Model\Ui\ConfigProvider;

class OrderCancelAfter extends AbstractObserver
{
    protected $logger;

    protected $adapter;

    protected $orderRepository;

    protected $config;

    public function __construct(
        Logger $logger,
        TamaraAdapterFactory $adapter,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
    }


    public function execute(Observer $observer)
    {
        $this->logger->debug(['Start to cancel event']);

        if (!$this->config->getTriggerActions()) {
            $this->logger->debug(['Turned off the trigger actions']);
            return;
        }

        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        if ($payment === null) {
            return;
        }

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId($order->getId());

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['order_id'] = $order->getId();
        $data['total_amount'] = $order->getGrandTotal();
        $data['tax_amount'] = $order->getTaxAmount();
        $data['shipping_amount'] = $order->getShippingAmount();
        $data['discount_amount'] = $order->getDiscountAmount();
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['items'] = $order->getAllVisibleItems();

        $tamaraAdapter = $this->adapter->create();
        $tamaraAdapter->cancel($data);

        $this->logger->debug(['End to cancel event']);
    }
}