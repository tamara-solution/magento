<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Resolver;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;
use Tamara\Checkout\Model\Config\Source\AutomaticallyInvoice;
use Tamara\Checkout\Model\Config\Source\EmailTo\Options;
use Tamara\Checkout\Model\OrderRepository;
use Tamara\Request\Order\AuthoriseOrderRequest;
use Tamara\Response\Order\GetOrderResponse;

/**
 * Helper class for authorizing Tamara orders
 */
class OrderAuthorization extends AbstractData
{
    /**
     * @var OrderRepository
     */
    private $tamaraOrderRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Tamara\Checkout\Helper\Transaction
     */
    private $tamaraTransactionHelper;

    /**
     * @var Invoice
     */
    private $tamaraInvoiceHelper;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var OrderCommentSender
     */
    private $orderCommentSender;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param Resolver $locale
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\CacheInterface $magentoCache
     * @param BaseConfig $tamaraConfig
     * @param TamaraAdapterFactory $tamaraAdapterFactory
     * @param OrderRepository $tamaraOrderRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param Transaction $tamaraTransactionHelper
     * @param Invoice $tamaraInvoiceHelper
     * @param OrderSender $orderSender
     * @param OrderCommentSender $orderCommentSender
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        Resolver $locale,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\CacheInterface $magentoCache,
        BaseConfig $tamaraConfig,
        TamaraAdapterFactory $tamaraAdapterFactory,
        OrderRepository $tamaraOrderRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Tamara\Checkout\Helper\Transaction $tamaraTransactionHelper,
        Invoice $tamaraInvoiceHelper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender
    ) {
        parent::__construct($context, $locale, $storeManager, $magentoCache, $tamaraConfig, $tamaraAdapterFactory);
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->orderRepository = $orderRepository;
        $this->tamaraTransactionHelper = $tamaraTransactionHelper;
        $this->tamaraInvoiceHelper = $tamaraInvoiceHelper;
        $this->orderSender = $orderSender;
        $this->orderCommentSender = $orderCommentSender;
    }

    /**
     * Authorize a Tamara order
     *
     * @param \Magento\Sales\Model\Order $order Magento order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder Tamara order
     * @param int $storeId Store ID
     * @param GetOrderResponse $remoteOrder
     * @return bool True if authorization was successful, false otherwise
     * @throws LocalizedException
     */
    public function authorizeOrder($order, $tamaraOrder, $storeId, $remoteOrder)
    {
        try {
            if ($tamaraOrder->getIsAuthorised()) {
                // Local flag is set; only repair Magento pending state without repeating side effects.
                return $this->applyAuthorization($order, $tamaraOrder, $storeId, $remoteOrder);
            }
            $tamaraOrderId = $tamaraOrder->getTamaraOrderId();
            $this->log(["Starting authorization for order " . $order->getId() . " with Tamara order ID " . $tamaraOrderId]);

            $adapter = $this->tamaraAdapterFactory->create($storeId);
            $client = $adapter->getClient();

            // Print current time before call Tamara Authorize API
            $this->log(["Current time before call Tamara Authorize API: " . date('Y-m-d H:i:s')]);

            // Authorize the order with Tamara
            $response = $client->authoriseOrder(new AuthoriseOrderRequest($tamaraOrderId));

            if (!$response->isSuccess() && $response->getStatusCode() !== 409) {
                $this->log(["Error when authorize order " . $order->getId() => $response->getMessage()], true);
                return false;
            }
            if ($response->getStatusCode() === 409) {
                return $this->applyAuthorization($order, $tamaraOrder, $storeId, $remoteOrder);
            }
            if (!in_array($response->getOrderStatus(), ['authorised', 'fully_captured'])) {
                $this->log(["The order status is not accepted, status: " . $response->getOrderStatus(), true]);
                return false;
            }

            return $this->applyAuthorization($order, $tamaraOrder, $storeId, $remoteOrder);
        } catch (\Exception $exception) {
            $this->log(["Error when authorize order " . $order->getId() => $exception->getMessage()], true);
            return false;
        }
    }

    /**
     * Apply Magento-side authorisation when Tamara already reports the order as authorised.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param int $storeId
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @return bool
     */
    public function syncAuthorizedOrder($order, $tamaraOrder, $storeId, $remoteOrder)
    {
        try {
            return $this->applyAuthorization($order, $tamaraOrder, $storeId, $remoteOrder);
        } catch (\Exception $exception) {
            $this->log(["Error when synchronizing authorised order " . $order->getId() => $exception->getMessage()], true);
            return false;
        }
    }

    /**
     * Synchronize a partially captured order without marking the grand total paid.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param int $storeId
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @param float $capturedAmount
     * @return bool
     */
    public function syncPartiallyCapturedOrder($order, $tamaraOrder, $storeId, $remoteOrder, $capturedAmount)
    {
        try {
            $adapter = $this->tamaraAdapterFactory->create($storeId);
            $paymentMethod = $this->getPaymentMethod($remoteOrder);
            $numberOfInstallments = $remoteOrder->getInstalments();
            $currentPaymentMethod = $order->getPayment()->getMethod();

            $tamaraOrder->setIsAuthorised(1);
            $tamaraOrder->setPaymentType($paymentMethod);
            $tamaraOrder->setNumberOfInstallments($numberOfInstallments);
            $this->tamaraOrderRepository->save($tamaraOrder);

            if ($currentPaymentMethod != $paymentMethod) {
                $order->getPayment()->setMethod($paymentMethod);
            }

            $grandTotal = (float) $order->getGrandTotal();
            $baseGrandTotal = (float) $order->getBaseGrandTotal();
            $capturedAmount = min(max((float) $capturedAmount, 0.0), $grandTotal);
            $baseCapturedAmount = $grandTotal > 0
                ? $capturedAmount * $baseGrandTotal / $grandTotal
                : 0.0;

            $order->setTotalPaid($capturedAmount);
            $order->setTotalDue(max($grandTotal - $capturedAmount, 0.0));
            $order->setBaseTotalPaid($baseCapturedAmount);
            $order->setBaseTotalDue(max($baseGrandTotal - $baseCapturedAmount, 0.0));
            $order->getPayment()->setAmountPaid($capturedAmount);
            $order->getPayment()->setAmountAuthorized($grandTotal);
            $order->getPayment()->setBaseAmountPaid($baseCapturedAmount);
            $order->getPayment()->setBaseAmountPaidOnline($baseCapturedAmount);
            $order->getPayment()->setBaseAmountAuthorized($baseGrandTotal);
            $this->orderRepository->save($order);

            if ($currentPaymentMethod != $paymentMethod) {
                $adapter->updatePaymentMethodToDbDirectly($order->getId(), $paymentMethod);
            }

            return true;
        } catch (\Exception $exception) {
            $this->log([
                "Error when synchronizing partially captured order " . $order->getId()
                    => $exception->getMessage()
            ], true);
            return false;
        }
    }

    /**
     * Persist the local side effects of a successful Tamara authorisation.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Tamara\Checkout\Model\Order $tamaraOrder
     * @param int $storeId
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @return bool
     */
    private function applyAuthorization($order, $tamaraOrder, $storeId, $remoteOrder)
    {
        $adapter = $this->tamaraAdapterFactory->create($storeId);
        $alreadyAuthorised = (bool) $tamaraOrder->getIsAuthorised();
        $isPending = in_array(
            $order->getState(),
            [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT],
            true
        );

        // First-time authorisation, or repair a stuck pending Magento order.
        if ($alreadyAuthorised && !$isPending) {
            return true;
        }

        // Update Tamara order
        $tamaraOrder->setIsAuthorised(1);

        // Resolve the placeholder to the payment method selected by Tamara.
        $currentPaymentMethod = $order->getPayment()->getMethod();
        $numberOfInstallments = $remoteOrder->getInstalments();
        $paymentMethod = $this->getPaymentMethod($remoteOrder);
        $tamaraOrder->setPaymentType($paymentMethod);
        $tamaraOrder->setNumberOfInstallments($numberOfInstallments);
        $this->tamaraOrderRepository->save($tamaraOrder);
        if ($currentPaymentMethod != $paymentMethod) {
            $order->getPayment()->setMethod($paymentMethod);
        }

        // Always leave pending so cron/success do not re-authorise this order.
        $authoriseStatus = $this->tamaraConfig->getCheckoutAuthoriseStatus($storeId);
        if (empty($authoriseStatus)) {
            $authoriseStatus = Order::STATE_PROCESSING;
        }
        $order->setState(Order::STATE_PROCESSING)->setStatus($authoriseStatus);

        // Set payment amounts
        $grandTotal = $order->getGrandTotal();
        $order->setTotalDue(0.00);
        $order->setTotalPaid($grandTotal);
        $order->getPayment()->setAmountPaid($grandTotal);
        $order->getPayment()->setAmountAuthorized($grandTotal);
        $baseAmountPaid = $order->getBaseGrandTotal();
        $order->setBaseTotalDue(0.00);
        $order->setBaseTotalPaid($baseAmountPaid);
        $order->getPayment()->setBaseAmountPaid($baseAmountPaid);
        $order->getPayment()->setBaseAmountAuthorized($baseAmountPaid);
        $order->getPayment()->setBaseAmountPaidOnline($baseAmountPaid);

        // Side effects only on the first successful local authorisation.
        if (!$alreadyAuthorised) {
            try {
                $this->orderSender->send($order);
            } catch (\Exception $exception) {
                $this->log(["Error when sending order email to the customer" => $exception->getMessage()], true);
            }

            $authorisedAmount = $order->getOrderCurrency()->formatTxt($order->getGrandTotal());
            $authoriseComment = __('Tamara - order was authorised. The authorised amount is %1.', $authorisedAmount);
            $this->tamaraInvoiceHelper->log(["Create transaction after authorise payment"]);
            $this->tamaraTransactionHelper->saveAuthoriseTransaction($authoriseComment, $order, $order->getIncrementId());

            if (in_array(Options::SEND_EMAIL_WHEN_AUTHORISE, $this->tamaraConfig->getSendEmailWhen($order->getStoreId()))) {
                try {
                    $this->orderCommentSender->send($order, true, $authoriseComment);
                } catch (\Exception $exception) {
                    $this->log(["Error when sending authorise notification" => $exception->getMessage()], true);
                }
                $order->addStatusHistoryComment(
                    __('Notified customer about order #%1 was authorised.', $order->getIncrementId()),
                    $authoriseStatus
                )->setIsCustomerNotified(true);
            }
        } else {
            $order->addStatusHistoryComment(
                __('Tamara - repaired Magento pending state for an already authorised order.'),
                $authoriseStatus
            );
        }

        $this->orderRepository->save($order);

        if (!$alreadyAuthorised) {
            if ($this->tamaraConfig->getAutoGenerateInvoice($order->getStoreId()) == AutomaticallyInvoice::GENERATE_AFTER_AUTHORISE) {
                $this->log(["Automatically generate invoice after authorise payment"]);
                $this->tamaraInvoiceHelper->generateInvoice($order->getId());
            }

            $captureComment = __('Magento capture transaction created.');
            $captureTransactionId = $order->getIncrementId() . "-" . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
            $this->tamaraTransactionHelper->createTransaction($order, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, $captureComment, $captureTransactionId);
        }

        // Update the payment method for Magento order if the status from the remote is different from the current method
        if ($currentPaymentMethod != $paymentMethod) {
            // Keep the payment and sales grid records reconciled after the order save.
            $adapter->updatePaymentMethodToDbDirectly($order->getId(), $paymentMethod);
        }

        $this->log(["Successfully authorized order " . $order->getId()]);
        return true;
    }

    /**
     * @param \Tamara\Response\Order\GetOrderByReferenceIdResponse $remoteOrder
     * @return string
     */
    private function getPaymentMethod($remoteOrder)
    {
        $numberOfInstallments = $remoteOrder->getInstalments();
        if (!empty($numberOfInstallments)) {
            $paymentMethod = \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE;
        } else {
            $paymentMethod = \Tamara\Checkout\Gateway\Config\BaseConfig::convertPaymentMethodFromTamaraToMagento(
                $remoteOrder->getPaymentType()
            );
        }
        if (!empty($numberOfInstallments)
            && $paymentMethod == \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE
            && $numberOfInstallments < 13
            && $numberOfInstallments != 3
        ) {
            $paymentMethod .= "_" . $numberOfInstallments;
        }
        return $paymentMethod;
    }
}
