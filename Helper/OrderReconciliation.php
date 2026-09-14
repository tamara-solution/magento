<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\Registry;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Api\OrderRepositoryInterface as TamaraOrderRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;
use Tamara\Request\Order\GetOrderByReferenceIdRequest;
use Tamara\Request\Order\GetOrderRequest;

/**
 * Reconciles a pending Magento order with its current state in Tamara.
 */
class OrderReconciliation
{
    const STATUS_APPROVED = 'approved';
    const STATUS_AUTHORISED = 'authorised';
    const STATUS_FULLY_CAPTURED = 'fully_captured';
    const STATUS_PARTIALLY_CAPTURED = 'partially_captured';
    const STATUS_CAPTURED = 'captured';

    private const TERMINAL_STATUSES = ['expired', 'declined', 'canceled', 'cancelled', 'refunded'];
    private const CAPTURED_STATUSES = [
        self::STATUS_FULLY_CAPTURED,
        self::STATUS_PARTIALLY_CAPTURED,
        self::STATUS_CAPTURED
    ];
    /** HTTP statuses that mean the Tamara order itself cannot be resolved later. */
    private const PERMANENT_LOOKUP_FAILURE_STATUSES = [404];

    /** @var bool */
    private $lastLookupWasPermanentFailure = false;

    /** @var TamaraAdapterFactory */
    private $tamaraAdapterFactory;

    /** @var OrderAuthorization */
    private $orderAuthorization;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var TamaraOrderRepositoryInterface */
    private $tamaraOrderRepository;

    /** @var OrderManagementInterface */
    private $orderManagement;

    /** @var BaseConfig */
    private $config;

    /** @var Registry */
    private $registry;

    /** @var AbstractData */
    private $helper;

    public function __construct(
        TamaraAdapterFactory $tamaraAdapterFactory,
        OrderAuthorization $orderAuthorization,
        OrderRepositoryInterface $orderRepository,
        TamaraOrderRepositoryInterface $tamaraOrderRepository,
        OrderManagementInterface $orderManagement,
        BaseConfig $config,
        Registry $registry,
        AbstractData $helper
    ) {
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->orderAuthorization = $orderAuthorization;
        $this->orderRepository = $orderRepository;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->orderManagement = $orderManagement;
        $this->config = $config;
        $this->registry = $registry;
        $this->helper = $helper;
    }

    /**
     * Whether the most recent getRemoteOrder() failure was a definitive 404 (order not found).
     * Auth failures (403) are not permanent: a bad/rotated token must not cancel Magento orders.
     *
     * @return bool
     */
    public function wasLastLookupPermanentFailure()
    {
        return $this->lastLookupWasPermanentFailure;
    }

    /**
     * Pending Tamara orders historically used Magento's new state. Accept both
     * that state and pending_payment so existing and partially synchronized
     * orders remain reconcilable.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @return bool
     */
    public function isPendingOrder($order, $tamaraOrder)
    {
        return in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT], true);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @return string|null The latest Tamara status, or null when lookup failed.
     */
    public function reconcile($order, $tamaraOrder)
    {
        if (!$this->isPendingOrder($order, $tamaraOrder)) {
            return null;
        }

        $storeId = (int) $order->getStoreId();
        $remoteOrder = $this->getRemoteOrder($order, $tamaraOrder, $storeId);
        if ($remoteOrder === null) {
            // Only 404 (order not found) is permanent: cancel Magento to release inventory
            // and leave cron. 403 (auth) and timeouts/5xx stay pending for retry.
            if ($this->lastLookupWasPermanentFailure) {
                $this->cancelOrder($order, $tamaraOrder, 'not_found');
                return 'not_found';
            }
            return null;
        }

        $status = strtolower((string) $remoteOrder->getStatus());
        $captureIds = $this->getTransactionIds($remoteOrder, 'captures');

        // Terminal statuses must win over capture IDs: refunded/canceled orders still
        // expose prior captures and must not be marked paid/processing.
        if ($status === self::STATUS_APPROVED) {
            $this->orderAuthorization->authorizeOrder($order, $tamaraOrder, $storeId, $remoteOrder);
        } elseif (in_array($status, self::TERMINAL_STATUSES, true)) {
            $this->cancelOrder($order, $tamaraOrder, $status);
        } elseif (in_array($status, self::CAPTURED_STATUSES, true) || !empty($captureIds)) {
            $this->synchronizeCapturedOrder($order, $tamaraOrder, $storeId, $remoteOrder, $captureIds);
        } elseif ($status === self::STATUS_AUTHORISED) {
            $this->orderAuthorization->syncAuthorizedOrder($order, $tamaraOrder, $storeId, $remoteOrder);
        }

        // Always obtain a fresh representation after any Tamara-side action.
        $latestRemoteOrder = $this->getRemoteOrder($order, $tamaraOrder, $storeId);
        if ($latestRemoteOrder !== null) {
            $this->saveMetadata($order, $tamaraOrder, $latestRemoteOrder);
            return strtolower((string) $latestRemoteOrder->getStatus());
        }

        return $status;
    }

    /**
     * Look up by Tamara ID first, then by Magento increment ID as a fallback.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param int $storeId
     * @return \Tamara\Response\Order\GetOrderByReferenceIdResponse|null
     */
    private function getRemoteOrder($order, $tamaraOrder, $storeId)
    {
        $this->lastLookupWasPermanentFailure = false;
        $sawPermanentFailure = false;
        $sawTransientFailure = false;
        $client = $this->tamaraAdapterFactory->create($storeId)->getClient();
        $tamaraOrderId = (string) $tamaraOrder->getTamaraOrderId();

        if ($tamaraOrderId !== '') {
            try {
                $response = $client->getOrder(new GetOrderRequest($tamaraOrderId));
                if ($response->isSuccess()) {
                    return $response;
                }
                if ($this->isPermanentLookupFailure($response->getStatusCode())) {
                    $sawPermanentFailure = true;
                } else {
                    $sawTransientFailure = true;
                }
                $this->helper->log([
                    'Tamara order lookup failed for Magento order ' . $order->getId(),
                    'status_code' => $response->getStatusCode()
                ], true);
            } catch (\Exception $exception) {
                $sawTransientFailure = true;
                $this->helper->log([
                    'Tamara order lookup failed for Magento order ' . $order->getId()
                        => $exception->getMessage()
                ], true);
            }
        }

        try {
            $response = $client->getOrderByReferenceId(
                new GetOrderByReferenceIdRequest((string) $order->getIncrementId())
            );
            if ($response->isSuccess()) {
                if ($tamaraOrderId === '' && $response->getOrderId()) {
                    $tamaraOrder->setTamaraOrderId($response->getOrderId());
                    $this->tamaraOrderRepository->save($tamaraOrder);
                }
                return $response;
            }
            if ($this->isPermanentLookupFailure($response->getStatusCode())) {
                $sawPermanentFailure = true;
            } else {
                $sawTransientFailure = true;
            }
            $this->helper->log([
                'Tamara reference lookup failed for Magento order ' . $order->getId(),
                'status_code' => $response->getStatusCode()
            ], true);
        } catch (\Exception $exception) {
            $sawTransientFailure = true;
            $this->helper->log([
                'Tamara reference lookup failed for Magento order ' . $order->getId()
                    => $exception->getMessage()
            ], true);
        }

        // Permanent only when every observed failure was definitive (no timeouts/5xx).
        $this->lastLookupWasPermanentFailure = $sawPermanentFailure && !$sawTransientFailure;
        return null;
    }

    /**
     * @param int|string|null $statusCode
     * @return bool
     */
    private function isPermanentLookupFailure($statusCode)
    {
        return in_array((int) $statusCode, self::PERMANENT_LOOKUP_FAILURE_STATUSES, true);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param int $storeId
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @param array $captureIds
     * @return void
     */
    private function synchronizeCapturedOrder($order, $tamaraOrder, $storeId, $remoteOrder, array $captureIds)
    {
        $capturedAmount = (float) $remoteOrder->getCapturedAmount()->getAmount();
        $totalAmount = (float) $remoteOrder->getTotalAmount()->getAmount();
        $isFullyCaptured = $capturedAmount + 0.0001 >= $totalAmount;
        if ($isFullyCaptured) {
            $isSynchronized = $this->orderAuthorization->syncAuthorizedOrder(
                $order,
                $tamaraOrder,
                $storeId,
                $remoteOrder
            );
        } else {
            $isSynchronized = $this->orderAuthorization->syncPartiallyCapturedOrder(
                $order,
                $tamaraOrder,
                $storeId,
                $remoteOrder,
                $capturedAmount
            );
        }
        if (!$isSynchronized) {
            return;
        }

        $captureType = $isFullyCaptured ? 'fully' : 'partially';
        $comment = __(
            'Order Payment is %1 captured on Tamara, Tamara Capture IDs: %2',
            $captureType,
            $captureIds ? implode(', ', $captureIds) : __('N/A')
        );

        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $order->addStatusHistoryComment($comment, Order::STATE_PROCESSING);
        $this->orderRepository->save($order);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param string $remoteStatus
     * @return void
     */
    private function cancelOrder($order, $tamaraOrder, $remoteStatus)
    {
        $registeredHere = false;
        try {
            if (!$this->registry->registry('skip_tamara_cancel')) {
                $this->registry->register('skip_tamara_cancel', true);
                $registeredHere = true;
            }

            if ($order->getState() !== Order::STATE_CANCELED) {
                $this->orderManagement->cancel($order->getId());
            }

            $order->setState(Order::STATE_CANCELED)
                ->setStatus($this->config->getCheckoutCancelStatus($order->getStoreId()));
            if ($remoteStatus === 'not_found') {
                $order->addStatusHistoryComment(
                    __('Tamara - Order was cancelled because the Tamara order could not be found (HTTP 404).'),
                    false
                );
            } else {
                $order->addStatusHistoryComment(
                    __('Tamara - Order was automatically cancelled because its Tamara status is %1.', $remoteStatus),
                    false
                );
            }
            $this->orderRepository->save($order);

            $tamaraOrder->setCanceledFromConsole(true);
            $this->tamaraOrderRepository->save($tamaraOrder);
        } finally {
            if ($registeredHere) {
                $this->registry->unregister('skip_tamara_cancel');
            }
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @return void
     */
    private function saveMetadata($order, $tamaraOrder, $remoteOrder)
    {
        $captureIds = $this->getTransactionIds($remoteOrder, 'captures');
        $cancelIds = $this->getTransactionIds($remoteOrder, 'cancels');
        $refundIds = $this->getTransactionIds($remoteOrder, 'refunds');
        $installments = $remoteOrder->getInstalments();
        $paymentType = (string) $remoteOrder->getPaymentType();

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('tamara_order_id', $remoteOrder->getOrderId());
        $payment->setAdditionalInformation('payment_type', $paymentType);
        $payment->setAdditionalInformation('number_of_installments', $installments);
        $payment->setAdditionalInformation('tamara_order_status', $remoteOrder->getStatus());
        $payment->setAdditionalInformation('capture_ids', $captureIds);
        $payment->setAdditionalInformation('cancel_ids', $cancelIds);
        $payment->setAdditionalInformation('refund_ids', $refundIds);
        $this->orderRepository->save($order);

        $tamaraOrder->setTamaraOrderId($remoteOrder->getOrderId());
        $tamaraOrder->setPaymentType($this->getMagentoPaymentType($paymentType, $installments));
        $tamaraOrder->setNumberOfInstallments($installments);
        $this->tamaraOrderRepository->save($tamaraOrder);
    }

    /**
     * @param string $paymentType
     * @param int|null $installments
     * @return string
     */
    private function getMagentoPaymentType($paymentType, $installments)
    {
        if (!empty($installments)) {
            $method = \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE;
            if ((int) $installments < 13 && (int) $installments !== 3) {
                $method .= '_' . (int) $installments;
            }
            return $method;
        }

        return BaseConfig::convertPaymentMethodFromTamaraToMagento($paymentType);
    }

    /**
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @param string $transactionType
     * @return array
     */
    private function getTransactionIds($remoteOrder, $transactionType)
    {
        try {
            $accessors = [
                'captures' => ['getCaptures', 'getCaptureId'],
                'cancels' => ['getCancels', 'getCancelId'],
                'refunds' => ['getRefunds', 'getRefundId']
            ];
            if (!isset($accessors[$transactionType])) {
                return [];
            }

            list($collectionGetter, $idGetter) = $accessors[$transactionType];
            $items = $remoteOrder->getTransactions()->{$collectionGetter}()->getIterator();
            $ids = [];
            foreach ($items as $item) {
                $id = $item->{$idGetter}();
                if (!empty($id)) {
                    $ids[] = (string) $id;
                }
            }
            return array_values(array_unique($ids));
        } catch (\Exception $exception) {
            $this->helper->log([
                'Cannot read Tamara ' . $transactionType . ' metadata' => $exception->getMessage()
            ], true);
            return [];
        }
    }
}
