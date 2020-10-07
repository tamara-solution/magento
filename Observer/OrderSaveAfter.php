<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
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
     * @var TamaraAdapterFactory
     */
    protected $adapter;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

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
        TamaraAdapterFactory $adapter,
        OrderRepositoryInterface $orderRepository,
        ProductHelper $productHelper,
        BaseConfig $config,
        \Tamara\Checkout\Helper\Capture $captureHelper
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->orderRepository = $orderRepository;
        $this->productHelper = $productHelper;
        $this->config = $config;
        $this->captureHelper = $captureHelper;
    }


    public function execute(Observer $observer)
    {
        $this->logger->debug(['Start to order save after event']);

        if (!$this->config->getTriggerActions()) {
            $this->logger->debug(['Turned off the trigger actions']);
            return;
        }

        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $this->captureOrderWhenChangeStatus($order);

        $this->logger->debug(['End to order save after event']);
    }


    /**
     * @param Order $order
     * @throws \Magento\Framework\Exception\IntegrationException
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    protected function captureOrderWhenChangeStatus(Order $order): void
    {
        if (!$this->captureHelper->canCapture($order)) {
            $this->logger->debug(['Order cannot capture'], null, $this->config->enabledDebug());
            return;
        }

        if (empty($this->config->getOrderStatusShouldBeCaptured())) {
            $this->logger->debug(['Capture when order status change is not set, skip capture'], null,
                $this->config->enabledDebug());
            return;
        }

        if ($order->getStatus() != $this->config->getOrderStatusShouldBeCaptured()) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }
        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId($order->getId());
        $data['order_id'] = $order->getId();
        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['total_amount'] = $order->getGrandTotal();
        $data['tax_amount'] = $order->getTaxAmount();
        $data['shipping_amount'] = $order->getShippingAmount();
        $data['discount_amount'] = $order->getDiscountAmount();
        $data['shipping_info'] = $order->getTracksCollection()->toArray() ?? [];
        $data['currency'] = $order->getOrderCurrencyCode();

        $data['items'] = [];
        foreach ($order->getItems() as $orderItem) {
            $totalAmount = $this->getRowTotalItem($orderItem);
            if (empty($totalAmount)) {
                continue;
            }
            $itemTemp = [];
            $itemTemp['order_item_id'] = $orderItem->getItemId();
            $itemTemp['type'] = $orderItem->getProductType();
            $itemTemp['total_amount'] = $totalAmount;
            $itemTemp['tax_amount'] = $orderItem->getTaxAmount();
            $itemTemp['discount_amount'] = $orderItem->getDiscountAmount();
            $itemTemp['unit_price'] = $orderItem->getPrice();
            $itemTemp['name'] = $orderItem->getName();
            $itemTemp['sku'] = $orderItem->getSku();
            $itemTemp['quantity'] = $this->getQty($orderItem);
            $itemTemp['image_url'] = $this->productHelper->getImageFromProductId($orderItem->getProductId());
            $data['items'][] = $itemTemp;
        }

        $tamaraAdapter = $this->adapter->create();
        $this->logger->debug([sprintf('Capture when order status is %s', $order->getStatus())], null,
            $this->config->enabledDebug());
        $tamaraAdapter->capture($data, $order);
    }


    /**
     * @param OrderItemInterface $item
     * @return float
     */
    private function getRowTotalItem(OrderItemInterface $item): float
    {
        if ($item->getRowTotal() === null || !$item->getRowTotal()) {
            return 0.0;
        }

        return floatval($item->getRowTotal()
            - $item->getDiscountAmount()
            + $item->getTaxAmount()
            + $item->getDiscountTaxCompensationAmount());
    }

    /**
     * @param OrderItemInterface $item
     * @return int
     */
    private function getQty(OrderItemInterface $item): int
    {
        if ($qtyShipped = intval($item->getQtyShipped())) {
            return $qtyShipped;
        }
        if ($qtyInvoiced = intval($item->getQtyInvoiced())) {
            return $qtyInvoiced;
        }
        if ($qtyOrdered = intval($item->getQtyOrdered())) {
            return $qtyOrdered;
        }
    }
}