<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Api\CancelRepositoryInterface;
use Tamara\Checkout\Api\RefundRepositoryInterface;
use Tamara\Checkout\Model\OrderRepository;

class Cancel extends \Tamara\Checkout\Helper\AbstractData
{

    /**
     * @var \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory
     */
    protected $tamaraAdapterFactory;
    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $magentoOrderRepository;
    /**
     * @var OrderRepository
     */
    private $tamaraOrderRepository;

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
        \Tamara\Checkout\Model\OrderRepository $tamaraOrderRepository,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory,
        CancelRepositoryInterface $tamaraCancelRepository,
        RefundRepositoryInterface $tamaraRefundRepository
    ) {
        $this->magentoOrderRepository = $magentoOrderRepository;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->tamaraCancelRepository = $tamaraCancelRepository;
        $this->tamaraRefundRepository = $tamaraRefundRepository;
        parent::__construct($context, $locale, $storeManager, $magentoCache, $tamaraConfig, $tamaraAdapterFactory);
    }

    public function cancelOrder($orderId, $cancelAmount = null): void
    {
        $order = $this->magentoOrderRepository->get($orderId);

        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }
        if (!$this->isTamaraPayment($payment->getMethod())) {
            return;
        }

        if ($cancelAmount === null) {
            $cancelAmount = $this->getAmountToBeCanceled($order);
            if ($cancelAmount <= 0.00) {
                return;
            }
        }

        $this->log(['Start to cancel order, order id: ' . $orderId]);

        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['order_id'] = $order->getId();
        $data['total_amount'] = $cancelAmount;
        $data['tax_amount'] = $order->getTaxAmount();
        $data['shipping_amount'] = $order->getShippingAmount();
        $data['discount_amount'] = $order->getDiscountAmount();
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['items'] = [];

        $tamaraAdapter = $this->tamaraAdapterFactory->create($order->getStoreId());
        $tamaraAdapter->cancel($data);
    }


    /**
     * @param $order \Magento\Sales\Api\Data\OrderInterface
     * @return float|null
     */
    private function getAmountToBeCanceled($order) {
        $orderId = $order->getEntityId();
        $totalCanceled = $order->getTotalCanceled();
        if (!$totalCanceled) {
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
        return ($totalCanceled - $totalCanceledInTamara - $totalRefundedInTamara);
    }

    /**
     * @param $creditMemo \Magento\Sales\Model\Order\Creditmemo
     * @throws \Magento\Framework\Exception\IntegrationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function cancelOrderByCreditMemo($creditMemo) {
        $order = $creditMemo->getOrder();
        $this->log(['Start to cancel order by creditmemo, order id: ' . $order->getEntityId()]);
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($order->getEntityId());
        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['order_id'] = $order->getId();
        $data['total_amount'] = $creditMemo->getGrandTotal();
        $data['tax_amount'] = $creditMemo->getTaxAmount();
        $data['shipping_amount'] = $creditMemo->getShippingAmount();
        $data['discount_amount'] = $creditMemo->getDiscountAmount();
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['items'] = [];
        $data['is_authorised'] = $tamaraOrder->getIsAuthorised();
        $tamaraAdapter = $this->tamaraAdapterFactory->create($order->getStoreId());
        $tamaraAdapter->cancel($data);
    }
}