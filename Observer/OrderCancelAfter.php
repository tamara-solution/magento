<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class OrderCancelAfter extends AbstractObserver
{
    protected $logger;

    protected $coreRegistry;

    protected $adapter;

    protected $orderRepository;

    protected $config;

    public function __construct(
        Logger $logger,
        \Magento\Framework\Registry $coreRegistry,
        TamaraAdapterFactory $adapter,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config
    ) {
        $this->logger = $logger;
        $this->coreRegistry = $coreRegistry;
        $this->adapter = $adapter;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
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

        $this->logger->debug(['Tamara - Start to cancel event']);
        if (!$this->config->getTriggerActions($order->getStoreId())) {
            $this->logger->debug(['Tamara - Turned off the trigger actions']);
            return;
        }
        if (!in_array(\Tamara\Checkout\Model\Config\Source\TriggerEvents\Options::CANCEL_ORDER, $this->config->getTriggerEvents($order->getStoreId()))) {
            $this->logger->debug(['Tamara - Skip trigger cancel event']);
            return;
        }
        if ($this->coreRegistry->registry("skip_tamara_cancel") || $this->coreRegistry->registry("cancel_abandoned_order")) {
            $this->logger->debug(['Tamara - Skip tamara cancel']);
            return;
        }

        $tamaraAdapter = $this->adapter->create($order->getStoreId());

        try {
            $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId($order->getId());
        } catch (\Exception $exception) {
            //Tamara order doesn't exist
            return;
        }

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['order_id'] = $order->getId();
        $data['total_amount'] = $order->getGrandTotal();
        $data['tax_amount'] = $order->getTaxAmount();
        $data['shipping_amount'] = $order->getShippingAmount();
        $data['discount_amount'] = $order->getDiscountAmount();
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['items'] = $order->getAllVisibleItems();

        $tamaraAdapter->cancel($data);

        $this->logger->debug(['Tamara - End to cancel event']);
    }
}