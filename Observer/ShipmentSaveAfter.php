<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\DB\Transaction;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Magento\Payment\Model\Method\Logger;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;
use Tamara\Checkout\Model\Helper\ProductHelper;

class ShipmentSaveAfter extends AbstractObserver
{
    protected $logger;

    protected $invoiceDocumentFactory;

    protected $adapter;

    protected $orderRepository;

    protected $invoiceService;

    protected $transaction;

    protected $invoiceSender;

    protected $config;

    protected $productHelper;

    protected $shipmentSender;

    public function __construct(
        Logger $logger,
        Order\InvoiceDocumentFactory $invoiceDocumentFactory,
        TamaraAdapterFactory $adapter,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        Order\Email\Sender\InvoiceSender $invoiceSender,
        Order\Email\Sender\ShipmentSender $shipmentSender,
        BaseConfig $config,
        ProductHelper $productHelper
    )
    {
        $this->logger = $logger;
        $this->invoiceDocumentFactory = $invoiceDocumentFactory;
        $this->adapter = $adapter;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->config = $config;
        $this->productHelper = $productHelper;
        $this->shipmentSender = $shipmentSender;
    }

    public function execute(Observer $observer)
    {
        $this->logger->debug(['Start to shipment event']);

        if (!$this->config->getTriggerActions()) {
            $this->logger->debug(['Turned off the trigger actions']);
            return;
        }

        /** @var Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $shipment->getOrder();
        $payment = $order->getPayment();

        if ($payment === null) {
            return;
        }

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        $invoice = $this->invoiceDocumentFactory->create($order, $shipment->getItems());
        $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId($order->getId());

        $data['order_id'] = $order->getId();
        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['total_amount'] = $invoice->getGrandTotal();
        $data['tax_amount'] = $invoice->getTaxAmount();
        $data['shipping_amount'] = $invoice->getShippingAmount();
        $data['discount_amount'] = $invoice->getDiscountAmount();
        $data['shipping_info'] = $shipment->getTracksCollection()->toArray() ?? [];
        $data['currency'] = $order->getOrderCurrencyCode();

        $productTypes = [];
        foreach ($order->getItems() as $orderItem) {
            $productTypes[$orderItem->getItemId()] = $orderItem->getProductType();
        }

        $itemPrepareInvoices = [];
        $data['items'] = [];
        $itemInvoices = [];
        foreach ($invoice->getItems() as $invoiceItem) {
            $itemTemp = [];
            $itemTemp['order_item_id'] = $invoiceItem->getOrderItemId();
            $itemTemp['type'] = $productTypes[$invoiceItem->getOrderItemId()];
            $itemTemp['total_amount'] = $this->getRowTotalItem($invoiceItem);
            $itemTemp['tax_amount'] = $invoiceItem->getTaxAmount();
            $itemTemp['discount_amount'] = $invoiceItem->getDiscountAmount();
            $itemTemp['unit_price'] = $invoiceItem->getPrice();
            $itemTemp['name'] = $invoiceItem->getName();
            $itemTemp['sku'] = $invoiceItem->getSku();
            $itemTemp['quantity'] = $invoiceItem->getQty();
            $itemTemp['image_url'] = $this->productHelper->getImageFromProductId($invoiceItem->getProductId());

            if (!empty($itemTemp['total_amount'])) {
                $data['items'][] = $itemTemp;
                $itemPrepareInvoices[] = [$itemTemp['order_item_id'] => $itemTemp['quantity']];
                $itemInvoices[] = $invoiceItem;
            }
        }

        $tamaraAdapter = $this->adapter->create();
        $tamaraAdapter->capture($data);

        $invoiceReady = $this->invoiceService->prepareInvoice($order, $itemPrepareInvoices);
        $invoiceReady->setShippingAmount($invoice->getShippingAmount());
        $invoiceReady->setShippingInclTax($invoice->getShippingInclTax());
        $invoiceReady->setShippingTaxAmount($invoice->getShippingTaxAmount());
        $invoiceReady->setBaseShippingAmount($invoice->getBaseShippingAmount());
        $invoiceReady->setBaseShippingDiscountTaxCompensationAmnt($invoice->getBaseShippingDiscountTaxCompensationAmnt());
        $invoiceReady->setShippingDiscountTaxCompensationAmount($invoice->getShippingDiscountTaxCompensationAmount());

        $invoiceReady->setDiscountAmount($invoice->getDiscountAmount());
        $invoiceReady->setBaseDiscountAmount($invoice->getBaseDiscountAmount());
        $invoiceReady->setBaseDiscountTaxCompensationAmount($invoice->getBaseDiscountTaxCompensationAmount());

        $invoiceReady->setTaxAmount($invoice->getTaxAmount());
        $invoiceReady->setSubtotal($invoice->getSubtotal());
        $invoiceReady->setBaseSubtotal($invoice->getBaseSubtotal());
        $invoiceReady->setSubtotalInclTax($invoice->getSubtotalInclTax());
        $invoiceReady->setBaseSubtotalInclTax($invoice->getBaseSubtotalInclTax());

        $invoiceReady->setGrandTotal($invoice->getGrandTotal());
        $invoiceReady->setBaseGrandTotal($invoice->getBaseGrandTotal());
        $invoiceReady->setItems($itemInvoices);
        $invoiceReady->register();

        $transactionSave = $this->transaction->addObject($invoiceReady)->addObject($invoiceReady->getOrder());
        $transactionSave->save();

        try {
            $this->shipmentSender->send($shipment);

            $order->addCommentToStatusHistory(
                __('Notified customer about shipment #%1.', $shipment->getId())
            )
                ->setIsCustomerNotified(true)
                ->save();
        } catch (\Throwable $exception) {
            $this->logger->debug([$exception->getMessage()]);
        }

        if ($this->config->getSendEmailInvoice()) {
            $this->invoiceSender->send($invoiceReady);

            $order->addCommentToStatusHistory(
                __('Notified customer about invoice #%1.', $invoiceReady->getId())
            )
                ->setIsCustomerNotified(true)
                ->save();
        }

        $this->logger->debug(['End shipment event']);
    }

    /**
     * @param InvoiceItemInterface $item
     * @return float|null
     */
    private function getRowTotalItem($item)
    {
        if ($item->getRowTotal() === null || !$item->getRowTotal()) {
            return 0;
        }

        return $item->getRowTotal()
            - $item->getDiscountAmount()
            + $item->getTaxAmount()
            + $item->getDiscountTaxCompensationAmount();
    }

}
