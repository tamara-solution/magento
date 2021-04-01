<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Api\CaptureRepositoryInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface as TamaraOrderRepository;

class Refund extends \Tamara\Checkout\Helper\AbstractData
{

    /**
     * @var CaptureRepositoryInterface
     */
    protected $captureRepository;

    /**
     * @var TamaraOrderRepository
     */
    protected $tamaraOrderRepository;

    /**
     * @var \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory
     */
    protected $tamaraAdapterFactory;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $magentoOrderRepository;

    /**
     * @var Cancel
     */
    protected $tamaraCancelHelper;

    public function __construct(
        Context $context,
        \Magento\Framework\Locale\Resolver $locale,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\CacheInterface $magentoCache,
        \Tamara\Checkout\Gateway\Config\BaseConfig $tamaraConfig,
        \Magento\Sales\Model\OrderRepository $magentoOrderRepository,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory,
        CaptureRepositoryInterface $captureRepository,
        TamaraOrderRepository $tamaraOrderRepository,
        \Tamara\Checkout\Helper\Cancel $tamaraCancelHelper
    ) {
        $this->magentoOrderRepository = $magentoOrderRepository;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->captureRepository = $captureRepository;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->tamaraCancelHelper = $tamaraCancelHelper;
        parent::__construct($context, $locale, $storeManager, $magentoCache, $tamaraConfig, $tamaraAdapterFactory);
    }

    public function refundOrder($orderId)
    {
        $order = $this->magentoOrderRepository->get($orderId);
        $payment = $order->getPayment();

        if ($payment === null) {
            return;
        }

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        //cancel if the order was not captured
        $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $order->getId()]);
        if (!count($captures)) {
            $this->tamaraCancelHelper->cancelOrder($orderId);
            return;
        }

        $adjustFee = 0.00;
        $adjustRefund = 0.00;
        $extraFee = $adjustRefund - $adjustFee;

        $data['order_id'] = $order->getEntityId();
        $data['currency'] = $order->getOrderCurrencyCode();
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($order->getEntityId());

        $grandTotal = $order->getGrandTotal();

        $captureItems = $this->captureRepository->getCaptureItemsByConditions(['order_id' => $data['order_id']]);

        if (empty($captureItems)) {
            return;
        }

        $prepareCaptureItems = [];
        foreach ($captureItems as $captureItem) {
            $prepareCaptureItems[$captureItem['order_item_id']] = $captureItem;
        }

        $captureItemFounds = [];
        foreach ($order->getItems() as $itemMemo) {

            $itemId = $itemMemo->getItemId();
            if ($itemMemo->getRowTotal() > 0) {
                $prepareCaptureItems[$itemId]['quantity'] = $itemMemo->getQtyOrdered();
                $captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['items'][] = $prepareCaptureItems[$itemId];
                if (!isset($captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['total_amount_items'])) {
                    $captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['total_amount_items'] = 0;
                }
                $captureItemFounds[$prepareCaptureItems[$itemId]['capture_id']]['total_amount_items'] += $this->getTotalItemOfOrderItem($itemMemo);
            }
        }

        // Calculate for the first time
        foreach ($captureItemFounds as $captureId => $field) {
            $capture = $this->captureRepository->getCaptureById($captureId);
            $shippingAmount = $capture->getShippingAmount() > 0 ? $order->getShippingAmount() : $capture->getShippingAmount();

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
            $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $orderId]);
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
                    $captureItemFounds[$captureId]['shipping_amount'] = $order->getShippingAmount();
                    $captureItemFounds[$captureId]['discount_amount'] = 0;
                    $captureItemFounds[$captureId]['items'][] = $this->getFakeItem($capture['order_id'], $captureId,
                        $totalAmount);
                }
            }
        }

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['refunds'] = $captureItemFounds;

        $tamaraAdapter = $this->tamaraAdapterFactory->create();
        $tamaraAdapter->refund($data);
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $item
     * @return float|null
     */
    private function getTotalItemOfOrderItem($item)
    {
        if ($item->getRowTotal() === null || !$item->getRowTotal()) {
            return 0.00;
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
            'discount_amount' => 0,
            'image_url' => '',
        ];
    }

    public function refundOrderByCreditMemo($creditMemo)
    {
        /** @var Creditmemo $creditMemo */

        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();

        if ($payment === null) {
            return;
        }

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        //cancel if the order was not captured
        $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $order->getId()]);
        if (!count($captures)) {
            $this->tamaraCancelHelper->cancelOrder($order->getId());
            return;
        }

        $adjustFee = $creditMemo->getAdjustmentNegative();
        $adjustRefund = $creditMemo->getAdjustmentPositive();
        $extraFee = $adjustRefund - $adjustFee;

        $data['order_id'] = $creditMemo->getOrderId();
        $data['currency'] = $order->getOrderCurrencyCode();
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($order->getId());

        $grandTotal = $creditMemo->getGrandTotal();
        $data['refund_grand_total'] = $grandTotal;

        $captureItems = $this->captureRepository->getCaptureItemsByConditions(['order_id' => $data['order_id']]);

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
                    $captureItemFounds[$captureId]['items'][] = $this->getFakeItem($capture['order_id'], $captureId,
                        $totalAmount);
                }
            }
        }

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['refunds'] = $captureItemFounds;

        $tamaraAdapter = $this->tamaraAdapterFactory->create();
        $tamaraAdapter->refund($data);
    }

    /**
     * @param CreditmemoItemInterface $item
     * @return float|null
     */
    private function getTotalItem($item)
    {
        if ($item->getRowTotal() === null || !$item->getRowTotal()) {
            return 0.00;
        }

        return $item->getRowTotal()
            - $item->getDiscountAmount()
            + $item->getTaxAmount()
            + $item->getDiscountTaxCompensationAmount();
    }

}