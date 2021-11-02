<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Api\CancelRepositoryInterface;
use Tamara\Checkout\Api\RefundRepositoryInterface;
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

    /**
     * @var CancelRepositoryInterface
     */
    protected $tamaraCancelRepository;

    /**
     * @var RefundRepositoryInterface
     */
    protected $tamaraRefundRepository;

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
        \Tamara\Checkout\Helper\Cancel $tamaraCancelHelper,
        CancelRepositoryInterface $tamaraCancelRepository,
        RefundRepositoryInterface $tamaraRefundRepository
    ) {
        $this->magentoOrderRepository = $magentoOrderRepository;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->captureRepository = $captureRepository;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->tamaraCancelHelper = $tamaraCancelHelper;
        $this->tamaraCancelRepository = $tamaraCancelRepository;
        $this->tamaraRefundRepository = $tamaraRefundRepository;
        parent::__construct($context, $locale, $storeManager, $magentoCache, $tamaraConfig, $tamaraAdapterFactory);
    }

    /**
     * Fully refund order by order id
     * @param $orderId
     * @param $refundAmount
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\IntegrationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function refundOrder($orderId, $refundAmount = null)
    {
        $order = $this->magentoOrderRepository->get($orderId);
        $payment = $order->getPayment();

        if ($payment === null) {
            return;
        }

        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        if ($refundAmount === null) {
            $refundAmount = $this->getAmountToBeRefunded($order);
            if ($refundAmount <= 0.00) {
                return;
            }
        }

        //cancel if the order was not captured
        $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $order->getId()]);
        if (!count($captures)) {
            $this->tamaraCancelHelper->cancelOrder($orderId, $refundAmount);
            return;
        }

        $captureId = "";
        foreach ($captures as $capture) {
            $captureId = $capture['capture_id'];
            break;
        }

        $data['order_id'] = $orderId;
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['comment'] = "Refunded from Magento console";
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($order->getId());
        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['refund_grand_total'] = $refundAmount;

        $refund = [];
        $refund['total_amount'] = $refundAmount;
        $refund['shipping_amount'] = $order->getShippingAmount();
        $refund['tax_amount'] = $order->getTaxAmount();
        $refund['discount_amount'] = $order->getDiscountAmount();
        $refund['refunded_amount'] = $refundAmount;
        $refund['items'] = [];
        $data['refunds'] = [$captureId => $refund];
        $data['refund_from_memo'] = false;
        $tamaraAdapter = $this->tamaraAdapterFactory->create($order->getStoreId());
        $tamaraAdapter->refund($data);
    }

    /**
     * @param $order \Magento\Sales\Api\Data\OrderInterface
     * @return float|null
     */
    private function getAmountToBeRefunded($order) {
        $orderId = $order->getEntityId();
        $totalRefunded = $order->getTotalRefunded();
        if (!$totalRefunded) {
            return 0.00;
        }
        $tamaraCancels = $this->tamaraCancelRepository->getCancelsByOrderId($orderId);
        $totalCanceledInTamara = 0.00;
        foreach ($tamaraCancels as $tamaraCancel) {
            $request = $tamaraCancel->getRequest();
            if (!is_null($request)) {
                $totalCanceledInTamara += $request->total_amount->amount;
            }
        }
        $totalRefundedInTamara = 0.00;
        $tamaraRefunds = $this->tamaraRefundRepository->getRefundsByOrderId($orderId);
        foreach ($tamaraRefunds as $tamaraRefund) {
            $totalRefundedInTamara += $tamaraRefund->getRefundedAmount();
        }
        return ($totalRefunded - $totalCanceledInTamara - $totalRefundedInTamara);
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
            $this->tamaraCancelHelper->cancelOrderByCreditMemo($creditMemo);
            return;
        }

        $captureId = "";
        foreach ($captures as $capture) {
            $captureId = $capture['capture_id'];
            break;
        }

        $adjustFee = $creditMemo->getAdjustmentNegative();
        $adjustRefund = $creditMemo->getAdjustmentPositive();
        $extraFee = $adjustRefund - $adjustFee;
        $comment = "Refunded by Creditmemo, extra fee is {$extraFee}";
        $data['order_id'] = $creditMemo->getOrderId();
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['comment'] = $comment;
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($order->getId());
        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $grandTotal = $creditMemo->getGrandTotal();
        $data['refund_grand_total'] = $grandTotal;

        $refund = [];
        $refund['total_amount'] = $grandTotal;
        $refund['shipping_amount'] = $creditMemo->getShippingAmount();
        $refund['tax_amount'] = $creditMemo->getTaxAmount();
        $refund['discount_amount'] = $creditMemo->getDiscountAmount();
        $refund['refunded_amount'] = $grandTotal;
        $refund['items'] = [];
        $data['refunds'] = [$captureId => $refund];
        $data['refund_from_memo'] = true;
        $tamaraAdapter = $this->tamaraAdapterFactory->create($order->getStoreId());
        $tamaraAdapter->refund($data);
    }

}