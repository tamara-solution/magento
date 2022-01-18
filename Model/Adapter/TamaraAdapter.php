<?php

namespace Tamara\Checkout\Model\Adapter;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\Exception\IntegrationException;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Tamara\Checkout\Api\CancelRepositoryInterface;
use Tamara\Checkout\Api\CaptureRepositoryInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Api\RefundRepositoryInterface;
use Tamara\Checkout\Model\Helper\OrderHelper;
use Tamara\Checkout\Model\Helper\PaymentHelper;
use Tamara\Checkout\Model\Helper\StoreHelper;
use Tamara\Client;
use Tamara\Configuration;
use Tamara\Exception\RequestDispatcherException;
use Tamara\Model\Checkout\PaymentType;
use Tamara\Notification\NotificationService;
use Tamara\Request\Checkout\CreateCheckoutRequest;
use Tamara\Request\Order\AuthoriseOrderRequest;
use Tamara\Request\Webhook\RegisterWebhookRequest;
use Tamara\Request\Webhook\RemoveWebhookRequest;
use Tamara\Response\Checkout\CreateCheckoutResponse;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Tamara\Response\Checkout\GetPaymentTypesResponse;

class TamaraAdapter
{
    private const
        WEBHOOK_URL = 'tamara/payment/webhook',
        ALLOWED_WEBHOOKS = ['order_expired', 'order_declined'];
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CaptureRepositoryInterface
     */
    private $captureRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $mageRepository;

    /**
     * @var RefundRepositoryInterface
     */
    private $refundRepository;

    /**
     * @var CancelRepositoryInterface
     */
    private $cancelRepository;

    /**
     * @var string
     */
    private $checkoutAuthoriseStatus;

    private $orderSender;

    /**
     * @var Config
     */
    private $resourceConfig;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory
     */
    private $orderStatusCollectionFactory;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender
     */
    private $orderCommentSender;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var \Tamara\Checkout\Gateway\Config\BaseConfig
     */
    protected $baseConfig;

    /**
     * @var \Tamara\Checkout\Helper\Invoice
     */
    protected $tamaraInvoiceHelper;

    /**
     * @var \Tamara\Checkout\Helper\Transaction
     */
    protected $tamaraTransactionHelper;

    public function __construct(
        $apiUrl,
        $merchantToken,
        $notificationToken,
        $checkoutAuthoriseStatus,
        $orderRepository,
        $captureRepository,
        $mageRepository,
        $refundRepository,
        $cancelRepository,
        $logger,
        OrderSender $orderSender,
        Config $resourceConfig,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Tamara\Checkout\Gateway\Config\BaseConfig $baseConfig,
        \Tamara\Checkout\Helper\Invoice $tamaraInvoiceHelper,
        \Tamara\Checkout\Helper\Transaction $tamaraTransactionHelper
    )
    {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->notificationService = NotificationService::create($notificationToken);
        $config = Configuration::create($apiUrl, $merchantToken);
        $this->client = Client::create($config);
        $this->captureRepository = $captureRepository;
        $this->mageRepository = $mageRepository;
        $this->refundRepository = $refundRepository;
        $this->cancelRepository = $cancelRepository;
        $this->checkoutAuthoriseStatus = $checkoutAuthoriseStatus;
        $this->orderSender = $orderSender;
        $this->resourceConfig = $resourceConfig;
        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        $this->orderCommentSender = $orderCommentSender;
        $this->orderManagement = $orderManagement;
        $this->baseConfig = $baseConfig;
        $this->tamaraInvoiceHelper = $tamaraInvoiceHelper;
        $this->tamaraTransactionHelper = $tamaraTransactionHelper;
    }

    /**
     * @param string $countryCode
     * @param string $currencyCode
     * @return array
     * @throws RequestDispatcherException
     */
    public function getPaymentTypes(string $countryCode, $currencyCode = '')
    {
        if (empty($this->baseConfig->getMerchantToken())) {
            return [];
        }
        $response = $this->client->getPaymentTypes($countryCode, $currencyCode);
        if (!$response->isSuccess()) {
            $errorLogs = ["Tamara" => $response->getContent()];
            $this->logger->debug($errorLogs);
            return [];
        }
        return $this->parsePaymentTypesResponse($response);
    }

    /**
     * @param $response GetPaymentTypesResponse
     * @return array
     */
    public function parsePaymentTypesResponse($response) {
        $paymentTypes = [];
        if ($response->isSuccess()) {

            /** @var PaymentType $paymentType */
            foreach ($response->getPaymentTypes() as $paymentType) {
                $paymentTypeClone = $paymentType;
                if ($paymentTypeClone->getName() == \Tamara\Checkout\Gateway\Config\PayLaterConfig::PAY_BY_LATER) {
                    $paymentTypes[\Tamara\Checkout\Gateway\Config\PayLaterConfig::PAYMENT_TYPE_CODE] = [
                        'name' => \Tamara\Checkout\Gateway\Config\PayLaterConfig::PAYMENT_TYPE_CODE,
                        'min_limit' => $paymentTypeClone->getMinLimit()->getAmount(),
                        'max_limit' => $paymentTypeClone->getMaxLimit()->getAmount(),
                        'currency' => $paymentTypeClone->getMinLimit()->getCurrency(),
                        'description' => $paymentTypeClone->getDescription()
                    ];
                }
                if ($paymentTypeClone->getName() == \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAY_BY_INSTALMENTS) {
                    $description = $paymentTypeClone->getDescription();
                    if (count($installments = $paymentTypeClone->getSupportedInstalments())) {
                        foreach ($installments as $installment) {

                            /**
                             * @var \Tamara\Model\Checkout\Instalment $installment
                             */
                            $installmentMethodCode = \Tamara\Checkout\Gateway\Config\InstalmentConfig::getInstallmentPaymentCode($installment->getInstalments());
                            $installmentData = [
                                'name' => $installmentMethodCode,
                                'min_limit' => $installment->getMinLimit()->getAmount(),
                                'max_limit' => $installment->getMaxLimit()->getAmount(),
                                'currency' => $installment->getMinLimit()->getCurrency(),
                                'number_of_instalments' => $installment->getInstalments(),
                                'description' => $description
                            ];
                            $paymentTypes[$installmentMethodCode] = $installmentData;
                        }
                    }
                }
            }
        }
        return $paymentTypes;
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws RequestDispatcherException
     * @throws IntegrationException
     */
    public function createCheckout(array $data): array
    {
        $this->logger->debug(['Tamara - Start create checkout']);

        try  {
            $orderRequest = OrderHelper::createTamaraOrderFromArray($data);
            $result = $this->client->createCheckout(new CreateCheckoutRequest($orderRequest));
        } catch (\Exception $e) {
            $this->logger->debug(["Tamara - " . $e->getMessage()]);
            throw $e;
        }

        if (!$result->isSuccess()) {
            $errorLogs = [$result->getContent()];
            $message = $this->getErrorMessageFromResponse($result);
            $this->logger->debug(["Tamara" => $errorLogs]);
            throw new IntegrationException(__($message));
        }

        $checkoutResponse = $result->getCheckoutResponse();

        if ($checkoutResponse === null) {
            $this->logger->debug(['Tamara - CheckoutResponse was null, please check again']);
            throw new IntegrationException(__('The response is error, please ask administrator to help'));
        }

        return $checkoutResponse->toArray();
    }

    public function notification(): bool
    {
        $this->logger->debug(['Tamara - Start to notification']);
        try {
            $authoriseMessage = $this->notificationService->processAuthoriseNotification();
        } catch (\Exception $exception) {
            $this->logger->debug(["Tamara" => $exception->getMessage()]);

            return false;
        }

        try {
            // send confirmation to Tamara
           $response = $this->client->authoriseOrder(new AuthoriseOrderRequest($authoriseMessage->getOrderId()));

            if (!$response->isSuccess()) {
                $errorLogs = ["Tamara" => $response->getContent()];
                $this->logger->debug($errorLogs);

                return false;
            }

            $tamaraOrderId = $authoriseMessage->getOrderId();
            $order = $this->orderRepository->getTamaraOrderByTamaraOrderId($tamaraOrderId);
            $order->setIsAuthorised(1);
            $this->orderRepository->save($order);

            if (!empty($this->checkoutAuthoriseStatus)) {
                /** @var \Magento\Sales\Model\Order $mageOrder */
                $mageOrder = $this->mageRepository->get($order->getOrderId());
                $mageOrder->setState(Order::STATE_PROCESSING)->setStatus($this->checkoutAuthoriseStatus);

                //set base amount paid
                $grandTotal = $mageOrder->getGrandTotal();
                $mageOrder->setTotalDue(0.00);
                $mageOrder->setTotalPaid($grandTotal);
                $mageOrder->getPayment()->setAmountPaid($grandTotal);
                $mageOrder->getPayment()->setAmountAuthorized($grandTotal);
                $baseAmountPaid = $mageOrder->getBaseGrandTotal();
                $mageOrder->setBaseTotalDue(0.00);
                $mageOrder->setBaseTotalPaid($baseAmountPaid);
                $mageOrder->getPayment()->setBaseAmountPaid($baseAmountPaid);
                $mageOrder->getPayment()->setBaseAmountAuthorized($baseAmountPaid);
                $mageOrder->getPayment()->setBaseAmountPaidOnline($baseAmountPaid);
                $this->orderSender->send($mageOrder);

                $authorisedAmount = $mageOrder->getOrderCurrency()->formatTxt(
                    $mageOrder->getGrandTotal()
                );

                $authoriseComment = __('Tamara - order was authorised. The authorised amount is %1.', $authorisedAmount);
                $this->tamaraInvoiceHelper->log(["Create transaction after authorise payment"]);
                $this->tamaraTransactionHelper->saveAuthoriseTransaction($authoriseComment, $mageOrder, $mageOrder->getIncrementId());
                if (in_array(\Tamara\Checkout\Model\Config\Source\EmailTo\Options::SEND_EMAIL_WHEN_AUTHORISE, $this->baseConfig->getSendEmailWhen($mageOrder->getStoreId()))) {
                    try {
                        $this->orderCommentSender->send($mageOrder, true, $authoriseComment);
                    } catch (\Exception $exception) {
                        $this->logger->debug(["Tamara - Error when sending authorise notification: " . $exception->getMessage()]);
                    }
                    $mageOrder->addCommentToStatusHistory(
                        __('Notified customer about order #%1 was authorised.', $mageOrder->getIncrementId()),
                        $this->baseConfig->getCheckoutAuthoriseStatus($mageOrder->getStoreId())
                    )->setIsCustomerNotified(true)->save();
                }
                $this->mageRepository->save($mageOrder);

                if ($this->baseConfig->getAutoGenerateInvoice($mageOrder->getStoreId()) == \Tamara\Checkout\Model\Config\Source\AutomaticallyInvoice::GENERATE_AFTER_AUTHORISE) {
                    $this->tamaraInvoiceHelper->log(["Automatically generate invoice after authorise payment"]);
                    $this->tamaraInvoiceHelper->generateInvoice($mageOrder->getId());
                }

                //create capture transaction
                $captureComment = __('Magento capture transaction created.');
                $captureTransactionId = $mageOrder->getIncrementId() . "-" . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
                $this->tamaraTransactionHelper->createTransaction($mageOrder, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, $captureComment, $captureTransactionId);
                return true;
            }

        } catch (\Exception $exception) {
            $this->logger->debug(["Tamara - " . $exception->getMessage()]);
            return false;
        }

        $this->logger->debug(['Tamara - End notification']);

        return true;
    }

    public function capture(array $data, Order $order): void
    {
        $this->logger->debug(['Tamara - Start to capture']);

        try {
            $captureRequest = PaymentHelper::createCaptureRequestFromArray($data);
            $response = $this->client->capture($captureRequest);

            if (!$response->isSuccess() && $response->getStatusCode() !== 409) {
                $errorLogs = $response->getErrors() ?? [$response->getMessage()];
                $this->logger->debug(["Tamara" => $errorLogs]);
                throw new IntegrationException(__('Could not capture in tamara, please check log'));
            }

            $captureId = $response->getCaptureId();
            if (!empty($captureId)) {
                $data['capture_id'] = $captureId;
                $capture = PaymentHelper::createCaptureFromArray($data);
                $this->captureRepository->saveCapture($capture);

                $captureItems = [];
                foreach ($data['items'] as $itemData) {
                    $captureItem = PaymentHelper::createCaptureItemFromArray($itemData);
                    $captureItem->setOrderId($data['order_id']);
                    $captureItem->setCaptureId($captureId);
                    $captureItems[] = $captureItem->toArray();
                }

                $rows = $this->captureRepository->saveCaptureItems($captureItems);

                if (!$rows) {
                    $this->logger->debug(['Tamara - Cannot save capture items']);
                    $this->logger->debug($captureItems);
                    throw new IntegrationException(__('Cannot save capture items, please check log'));
                }

                $capturedAmount = $order->getOrderCurrency()->formatTxt(
                    $data['total_amount']
                );
                $captureComment = __('Tamara - order was captured. The captured amount is %1. Capture id is %2', $capturedAmount, $response->getCaptureId());
                $order->addCommentToStatusHistory($captureComment);
                $this->mageRepository->save($order);

                if ($this->baseConfig->getAutoGenerateInvoice($order->getStoreId()) == \Tamara\Checkout\Model\Config\Source\AutomaticallyInvoice::GENERATE_AFTER_CAPTURE) {
                    $this->logger->debug(["Tamara - Automatically generate invoice after capture payment"]);
                    $this->tamaraInvoiceHelper->generateInvoice($order->getId());
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug(["Tamara - " . $e->getMessage()]);
            throw new IntegrationException(__($e->getMessage()));
        }

        $this->logger->debug(['Tamara - End capture']);
    }

    public function refund(array $data): void
    {
        $this->logger->debug(['Tamara - Start to refund']);

        try {
            $refundRequest = PaymentHelper::createRefundRequestFromArray($data);
            $response = $this->client->refund($refundRequest);

            if (!$response->isSuccess() && $response->getStatusCode() !== 409) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug(["Tamara" => $errorLogs]);
                throw new IntegrationException(__($response->getMessage()));
            }

            $refunds = $response->getRefunds();
            $refundIds = [];
            foreach ($refunds as $refund) {
                $refundIds[$refund->getCaptureId()] = $refund->getRefundId();
            }

            foreach ($data['refunds'] as $captureId => $refund) {
                $capture = $this->captureRepository->getCaptureById($captureId);
                $totalRefundedAmount = $capture->getRefundedAmount() + $refund['refunded_amount'];
                $capture->setRefundedAmount($totalRefundedAmount);
                $this->captureRepository->saveCapture($capture);

                $refundModel = PaymentHelper::createRefundFromData(
                    $captureId,
                    $refundIds[$captureId],
                    $refundRequest->toArray(),
                    $data,
                    $refund,
                    $capture['total_amount']
                );

                $this->refundRepository->save($refundModel);
            }

            $magentoOrder = $this->mageRepository->get($data['order_id']);
            $creditMemoRefundedAmount = $magentoOrder->getOrderCurrency()->formatTxt(
                $data['refund_grand_total']
            );
            $refundTransactionId = $magentoOrder->getIncrementId() . '-refund';
                $refundComment = __('Tamara - order was refunded. The refunded amount is %1.', $creditMemoRefundedAmount);
            $this->tamaraTransactionHelper->createTransaction($magentoOrder, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND, $refundComment, $refundTransactionId);
            if (in_array(\Tamara\Checkout\Model\Config\Source\EmailTo\Options::SEND_EMAIL_WHEN_REFUND_ORDER, $this->baseConfig->getSendEmailWhen($magentoOrder->getStoreId()))) {
                $magentoOrder->setStatus($this->baseConfig->getOrderStatusShouldBeRefunded($magentoOrder->getStoreId()));
                try {
                    $this->orderCommentSender->send($magentoOrder, true, $refundComment);
                } catch (\Exception $exception) {
                    $this->logger->debug(["Tamara - Error when sending authorise notification: " . $exception->getMessage()]);
                }
                $magentoOrder->addCommentToStatusHistory(
                    __('Notified customer about order #%1 was refunded.', $magentoOrder->getIncrementId()),
                    $this->baseConfig->getOrderStatusShouldBeRefunded($magentoOrder->getStoreId())
                )->setIsCustomerNotified(true)->save();
            }
        } catch (\Exception $e) {
            $this->logger->debug(["Tamara - " . $e->getMessage()]);
            throw new IntegrationException(__("Cannot refund from Tamara, error: " . $e->getMessage()));
        }

        $this->logger->debug(['Tamara - End to refund']);
    }

    public function cancel(array $data): void
    {
        $this->logger->debug(['Tamara - Start to cancel']);

        try {
            $cancelRequest = PaymentHelper::createCancelRequestFromArray($data);
            $response = $this->client->cancelOrder($cancelRequest);

            if (!$response->isSuccess() && $response->getStatusCode() !== 499) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug(["Tamara" => $errorLogs]);
                throw new IntegrationException(__($response->getMessage()));
            }

            $cancel = PaymentHelper::createCancelFromResponse($response);
            $cancel->setOrderId($data['order_id']);
            $cancel->setRequest($cancelRequest->toArray());
            $this->cancelRepository->save($cancel);
            $mageOrder = $this->mageRepository->get($data['order_id']);
            $canceledAmount = $mageOrder->getOrderCurrency()->formatTxt(
                $data['total_amount']
            );
            $comment = __('Tamara - order was canceled, canceled amount is ' . $canceledAmount);
            $mageOrder->addCommentToStatusHistory(__($comment));
            $this->mageRepository->save($mageOrder);
            if (in_array(\Tamara\Checkout\Model\Config\Source\EmailTo\Options::SEND_EMAIL_WHEN_CANCEL_ORDER, $this->baseConfig->getSendEmailWhen($mageOrder->getStoreId()))) {
                if (!empty($data['is_authorised'])) {
                    try {
                        $this->orderCommentSender->send($mageOrder, true, $comment);
                    } catch (\Exception $exception) {
                        $this->logger->debug(["Tamara - Error when sending authorise notification: " . $exception->getMessage()]);
                    }
                    $mageOrder->addCommentToStatusHistory(
                        __('Notified customer about order #%1 was canceled.', $mageOrder->getIncrementId()),
                        $this->baseConfig->getCheckoutCancelStatus($mageOrder->getStoreId())
                    )->setIsCustomerNotified(true)->save();
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug(["Tamara - " . $e->getMessage()]);
            throw new IntegrationException(__($e->getMessage()));
        }

        $this->logger->debug(['Tamara - End to cancel']);
    }

    public function registerWebhook(): void
    {
        $this->logger->debug(['Tamara - Start to register webhook']);

        try {
            $scopeId = $this->baseConfig->getTamaraCore()->getCurrentScopeId();
            $scope = $this->baseConfig->getTamaraCore()->getCurrentScope();
            $baseUrl = $this->baseConfig->getScopeConfig()->getValue('web/secure/base_url', $scope, $scopeId);
            if (empty($baseUrl)) {
                $baseUrl = $this->baseConfig->getScopeConfig()->getValue('web/unsecure/base_url', $scope, $scopeId);
            }

            $param = "";
            if ($scope == \Magento\Store\Model\ScopeInterface::SCOPE_STORES) {
                $param = ("?storeId=" . $scopeId);
            }
            $webhookUrl = $baseUrl . self::WEBHOOK_URL . $param;

            $request = new RegisterWebhookRequest(
                $webhookUrl,
                self::ALLOWED_WEBHOOKS
            );

            $response = $this->client->registerWebhook($request);

            if (!$response->isSuccess()) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug(["Tamara" => $errorLogs]);
                throw new IntegrationException(__($response->getMessage()));
            }

            $webhookId = $response->getWebhookId();

            $this->resourceConfig->saveConfig(
                'payment/tamara_checkout/webhook_id',
                $webhookId,
                $scope,
                $scopeId
            );
        } catch (\Exception $exception) {
            $this->logger->debug(["Tamara - " . $exception->getMessage()]);

            throw $exception;
        }

        $this->logger->debug(['Tamara - End of register webhook']);
    }

    public function deleteWebhook($webhookId): void
    {
        $this->logger->debug(['Tamara - Start to delete webhook']);
        $scope = $this->baseConfig->getTamaraCore()->getCurrentScope();
        $scopeId = $this->baseConfig->getTamaraCore()->getCurrentScopeId();

        $this->resourceConfig->deleteConfig(
            'payment/tamara_checkout/webhook_id',
            $scope,
            $scopeId
        );

        $request = new RemoveWebhookRequest($webhookId);

        $response = $this->client->removeWebhook($request);

        if (!$response->isSuccess()) {
            $errorLogs = [$response->getContent()];
            $this->logger->debug(["Tamara" => $errorLogs]);
            throw new IntegrationException(__($response->getMessage()));
        }

        $this->logger->debug(['Tamara - End of delete webhook']);
    }

    public function webhook(): bool
    {
        $this->logger->debug(['Tamara - Start to webhook']);

        try {
            $webhookMessage = $this->notificationService->processWebhook();
            $eventType = $webhookMessage->getEventType();

            if (!in_array($eventType, self::ALLOWED_WEBHOOKS)) {
                $this->logger->debug([
                    'Event type: ' => $eventType,
                    'Webhook tamara order id: ' => $webhookMessage->getOrderId(),
                    'Webhook reference order id: ' => $webhookMessage->getOrderReferenceId(),
                ]);

                return false;
            }

            $tamaraOrderId = $webhookMessage->getOrderId();
            $order = $this->orderRepository->getTamaraOrderByTamaraOrderId($tamaraOrderId);
            if ($order->getIsAuthorised()) {
                return false;
            }

            /** @var \Magento\Sales\Model\Order $mageOrder */
            $mageOrder = $this->mageRepository->get($order->getOrderId());

            if ($mageOrder->getState() == Order::STATE_CANCELED || $mageOrder->getState() == Order::STATE_CLOSED) {
                $this->logger->debug([
                    __("Tamara - Magento order was canceled or closed, skip cancel by webhook")
                ]);
                return true;
            }
            $this->orderManagement->cancel($order->getOrderId());

            $mageOrder->setState(Order::STATE_CANCELED)->setStatus(Order::STATE_CANCELED);
            $comment = sprintf('Tamara - order was %s by webhook', $eventType);
            $mageOrder->addCommentToStatusHistory(__($comment));
            $mageOrder->getResource()->save($mageOrder);

        } catch (\Exception $exception) {
            $this->logger->debug(["Tamara - " . $exception->getMessage()]);
            return false;
        }

        $this->logger->debug(['Tamara - End Webhook']);
        return true;
    }

    /**
     * @param CreateCheckoutResponse $errorResponse
     *
     * @return string
     */
    private function getErrorMessageFromResponse($errorResponse): string
    {
        $message = $errorResponse->getMessage();

        if ($errorResponse->getErrors() === null) {
            return $message;
        }

        foreach ($errorResponse->getErrors() as $error) {
            $message = isset($error['error_code']) ? sprintf('%s, %s', $message, $error['error_code']) : $message;
        }

        return $message;
    }

    /**
     * @return Client
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * @param $magentoOrderIncrementId
     * @return \Tamara\Response\Order\GetOrderByReferenceIdResponse
     * @throws RequestDispatcherException
     */
    public function getTamaraOrderFromRemote($magentoOrderIncrementId) {
        return $this->getClient()->getOrderByReferenceId(new \Tamara\Request\Order\GetOrderByReferenceIdRequest($magentoOrderIncrementId));
    }
}
