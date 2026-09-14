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
     * Pending Tamara orders historically used Magento's new state. Accept both
     * that state and pending_payment so existing and partially synchronized
     * orders remain reconcilable.
     */
    public function isPendingOrder($order, $tamaraOrder): bool
    {
        return in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT], true);
    }

    /**
     * @return string|null The latest Tamara status, or null when lookup failed / not pending.
     */
    public function reconcile($order, $tamaraOrder)
    {
        if (!$this->isPendingOrder($order, $tamaraOrder)) {
            return null;
        }

        $storeId = (int) $order->getStoreId();
        $remoteOrder = $this->getRemoteOrder($order, $tamaraOrder, $storeId);
        if ($remoteOrder === null) {
            // Keep the order eligible for cron/success retries. Only push it to the
            // back of the sync queue so transient API failures do not starve others.
            $this->deferRetry($tamaraOrder, $order->getId());
            return null;
        }

        $status = strtolower((string) $remoteOrder->getStatus());
        $captureIds = $this->getTransactionIds($remoteOrder, 'captures');

        if ($status === self::STATUS_APPROVED) {
            $this->orderAuthorization->authorizeOrder($order, $tamaraOrder, $storeId, $remoteOrder);
        } elseif (in_array($status, self::CAPTURED_STATUSES, true) || !empty($captureIds)) {
            $this->synchronizeCapturedOrder($order, $tamaraOrder, $storeId, $remoteOrder, $captureIds);
        } elseif ($status === self::STATUS_AUTHORISED) {
            $this->orderAuthorization->syncAuthorizedOrder($order, $tamaraOrder, $storeId, $remoteOrder);
        } elseif (in_array($status, self::TERMINAL_STATUSES, true)) {
            $this->cancelOrder($order, $tamaraOrder, $status);
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
     */
    private function getRemoteOrder($order, $tamaraOrder, int $storeId)
    {
        $client = $this->tamaraAdapterFactory->create($storeId)->getClient();
        $tamaraOrderId = (string) $tamaraOrder->getTamaraOrderId();

        if ($tamaraOrderId !== '') {
            try {
                $response = $client->getOrder(new GetOrderRequest($tamaraOrderId));
                if ($response->isSuccess()) {
                    return $response;
                }
                $this->helper->log([
                    'Tamara order lookup failed for Magento order ' . $order->getId(),
                    'status_code' => $response->getStatusCode()
                ], true);
            } catch (\Exception $exception) {
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
            $this->helper->log([
                'Tamara reference lookup failed for Magento order ' . $order->getId(),
                'status_code' => $response->getStatusCode()
            ], true);
        } catch (\Exception $exception) {
            $this->helper->log([
                'Tamara reference lookup failed for Magento order ' . $order->getId()
                    => $exception->getMessage()
            ], true);
        }

        return null;
    }

    private function synchronizeCapturedOrder(
        $order,
        $tamaraOrder,
        int $storeId,
        $remoteOrder,
        array $captureIds
    ): void {
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

        // Keep the merchant-configured authorise status from syncAuthorizedOrder;
        // only ensure processing state + authorise status for the partial-capture path.
        $authoriseStatus = $this->config->getCheckoutAuthoriseStatus($storeId);
        if (!empty($authoriseStatus)) {
            $order->setState(Order::STATE_PROCESSING)->setStatus($authoriseStatus);
            $order->addCommentToStatusHistory($comment, $authoriseStatus, false);
        } else {
            $order->setState(Order::STATE_PROCESSING);
            $order->addCommentToStatusHistory($comment, false, false);
        }
        $this->orderRepository->save($order);
    }

    private function cancelOrder($order, $tamaraOrder, string $remoteStatus): void
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

            $storeId = $order->getStoreId();
            $magentoStatus = $this->getTerminalMagentoStatus($remoteStatus, $storeId);
            $order->setState(Order::STATE_CANCELED)->setStatus($magentoStatus);
            $order->addCommentToStatusHistory(
                __('Tamara - Order was automatically cancelled because its Tamara status is %1.', $remoteStatus),
                false,
                false
            );
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
     * Map Tamara terminal status to the matching merchant-configured Magento status.
     */
    private function getTerminalMagentoStatus(string $remoteStatus, $storeId): string
    {
        if ($remoteStatus === 'expired') {
            return (string) $this->config->getCheckoutExpireStatus($storeId);
        }
        if ($remoteStatus === 'declined') {
            return (string) $this->config->getCheckoutFailureStatus($storeId);
        }

        return (string) $this->config->getCheckoutCancelStatus($storeId);
    }

    /**
     * Bump updated_at so the cron (ordered by updated_at ASC) retries other
     * pending orders before this one. Never marks canceled_from_console —
     * timeouts/5xx/network errors must remain retryable from cron and success.
     */
    private function deferRetry($tamaraOrder, $magentoOrderId): void
    {
        try {
            $tamaraOrder->setData(
                'updated_at',
                (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')
            );
            $this->tamaraOrderRepository->save($tamaraOrder);
            $this->helper->log([
                'Deferred Tamara status sync retry for Magento order ' . $magentoOrderId
                    => 'lookup failed; will retry on a later run'
            ], true);
        } catch (\Exception $exception) {
            $this->helper->log([
                'Unable to defer Tamara status sync for Magento order ' . $magentoOrderId
                    => $exception->getMessage()
            ], true);
        }
    }

    private function saveMetadata($order, $tamaraOrder, $remoteOrder): void
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

    private function getMagentoPaymentType(string $paymentType, $installments): string
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

    private function getTransactionIds($remoteOrder, string $transactionType): array
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
