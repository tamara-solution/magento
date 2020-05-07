<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Tamara\Checkout\Api\CaptureRepositoryInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class CreditmemoSaveAfter extends AbstractObserver
{
    protected $logger;

    protected $adapter;

    protected $captureRepository;

    protected $orderRepository;

    protected $config;

    public function __construct(
        Logger $logger,
        TamaraAdapterFactory $adapter,
        CaptureRepositoryInterface $captureRepository,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->captureRepository = $captureRepository;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
    }

    public function execute(Observer $observer)
    {
        $this->logger->debug(['Start to creditmemo']);

        if (!$this->config->getTriggerActions()) {
            $this->logger->debug(['Turned off the trigger actions']);
            return;
        }

        /** @var Creditmemo $creditMemo */
        $creditMemo = $observer->getEvent()->getCreditmemo();

        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();

        if ($payment === null) {
            return;
        }

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        $adjustFee = $creditMemo->getAdjustmentNegative();
        $adjustRefund = $creditMemo->getAdjustmentPositive();
        $extraFee = $adjustRefund - $adjustFee;

        $data['order_id'] = $creditMemo->getOrderId();
        $data['currency'] = $order->getOrderCurrencyCode();
        $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId($order->getId());

        $grandTotal = $creditMemo->getGrandTotal();

        $captureItems = $this->captureRepository->getCaptureItemsByConditions(['order_id' =>  $data['order_id']]);

        if (empty($captureItems)) {
            return;
        }

        $prepareCaptureItems = [];
        foreach ($captureItems as $captureItem) {
            $prepareCaptureItems[$captureItem['order_item_id']] = $captureItem;
        }

        $captureItemFounds = [];
        foreach ($creditMemo->getItems() as $itemMemo) {

            $itemId = $itemMemo->getOrderItemId();
            if ($itemMemo->getRowTotal() > 0) {
                $prepareCaptureItems[$itemId]['quantity'] = $itemMemo->getQty();
                $captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['items'][] = $prepareCaptureItems[$itemId];
                if (!isset($captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['total_amount_items'])) {
                    $captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['total_amount_items'] = 0;
                }
                $captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['total_amount_items'] += $this->getTotalItem($itemMemo);
            }
        }

        // Calculate for the first time
        foreach ($captureItemFounds as $captureId => $field) {
            $capture = $this->captureRepository->getCaptureById($captureId);
            $shippingAmount = $capture->getShippingAmount() > 0 ? $creditMemo->getShippingAmount() : $capture->getShippingAmount();

            $taxAmount = $capture->getTaxAmount();
            $totalAmount = $shippingAmount + $captureItemFounds[$captureId]['total_amount_items'];
            $differenceRefund = $capture->getTotalAmount() - $capture->getRefundedAmount();

            if ($totalAmount + $extraFee <= $differenceRefund) {
                $totalAmount += $extraFee;
                $taxAmount += $extraFee;
            } else {
                $totalAmount = $differenceRefund;
                $taxAmount += $differenceRefund;
            }

            $grandTotal -= $totalAmount;

            $captureItemFounds[$captureId]['total_amount'] = $totalAmount;
            $captureItemFounds[$captureId]['refunded_amount'] = $totalAmount;
            $captureItemFounds[$captureId]['tax_amount'] = $taxAmount;
            $captureItemFounds[$captureId]['shipping_amount'] = $shippingAmount;
            $captureItemFounds[$captureId]['discount_amount'] = $capture->getDiscountAmount();

            // When the totalAmount is zero, we should not include it in refund request
            if (!$totalAmount) {
                unset($captureItemFounds[$captureId]);
            }
        }

        // If grand_total is greater than 0, its mean we have extra fee or shipping fee.
        if ($grandTotal > 0) {
            $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $creditMemo->getOrderId()]);
            foreach ($captures as $capture) {
                $captureId = $capture['capture_id'];
                $captureRefundedAmount = $capture['refunded_amount'];
                if (isset($captureItemFounds[$captureId]['refunded_amount'])) {
                    $captureRefundedAmount = $captureItemFounds[$captureId]['refunded_amount'] + $capture['refunded_amount'];
                }

                $refundAble = $capture['total_amount'] - $captureRefundedAmount;
                $totalAmount = ($grandTotal >= $refundAble) ? $refundAble : $grandTotal;
                $grandTotal -= $totalAmount;

                if ($totalAmount > 0) {
                    $captureItemFounds[$captureId]['total_amount'] = $totalAmount;
                    $captureItemFounds[$captureId]['refunded_amount'] = $totalAmount;
                    $captureItemFounds[$captureId]['tax_amount'] = $totalAmount;
                    $captureItemFounds[$captureId]['shipping_amount'] = $creditMemo->getShippingAmount();
                    $captureItemFounds[$captureId]['discount_amount'] = 0;
                    $captureItemFounds[$captureId]['items'][] = $this->getFakeItem($capture['order_id'], $captureId, $totalAmount);
                }
            }
        }

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['refunds'] = $captureItemFounds;

        $tamaraAdapter = $this->adapter->create();
        $tamaraAdapter->refund($data);
        $this->logger->debug(['End to creditmemo']);
    }

    /**
     * @param CreditmemoItemInterface $item
     * @return float|null
     */
    private function getTotalItem($item)
    {
        if ($item->getRowTotal() === null || !$item->getRowTotal()) {
            return 0;
        }

        return $item->getRowTotal()
            - $item->getDiscountAmount()
            + $item->getTaxAmount()
            + $item->getDiscountTaxCompensationAmount();
    }

    private function getFakeItem($orderId, $captureId, $totalAmount)
    {
        return [
            'order_item_id' => 1,
            'order_id' => $orderId,
            'capture_id' => $captureId,
            'name' => 'Extra fee item',
            'sku' => 'extra-fee',
            'type' => 'fee',
            'quantity' => 1,
            'unit_price' => 0,
            'total_amount' => $totalAmount,
            'tax_amount' => 0,
            'discount_amount' => 0
        ];
    }
}