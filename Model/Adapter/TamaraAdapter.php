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
        $this->baseConfig = $baseConfig;
        $this->tamaraInvoiceHelper = $tamaraInvoiceHelper;
        $this->tamaraTransactionHelper = $tamaraTransactionHelper;
    }

    /**
     * @param string $countryCode
     * @return array
     * @throws RequestDispatcherException
     */
    public function getPaymentTypes(string $countryCode)
    {
        $response = $this->client->getPaymentTypes($countryCode);
        if (!$response->isSuccess()) {
            $errorLogs = [$response->getContent()];
            $this->logger->debug($errorLogs);
            return [];
        }

        $paymentTypes = [];

        /** @var PaymentType $paymentType */
        foreach ($response->getPaymentTypes() as $paymentType) {
            $paymentTypes[$paymentType->getName()]['min_limit'] = $paymentType->getMinLimit()->getAmount();
            $paymentTypes[$paymentType->getName()]['max_limit'] = $paymentType->getMaxLimit()->getAmount();
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
        $this->logger->debug(['Start create checkout']);

        try  {
            $orderRequest = OrderHelper::createTamaraOrderFromArray($data);
            $result = $this->client->createCheckout(new CreateCheckoutRequest($orderRequest));
        } catch (\Exception $e) {
            $this->logger->debug([$e->getMessage()]);
            throw $e;
        }

        if (!$result->isSuccess()) {
            $errorLogs = [$result->getContent()];
            $message = $this->getErrorMessageFromResponse($result);
            $this->logger->debug($errorLogs);
            throw new IntegrationException(__($message));
        }

        $checkoutResponse = $result->getCheckoutResponse();

        if ($checkoutResponse === null) {
            $this->logger->debug(['CheckoutResponse was null, please check again']);
            throw new IntegrationException(__('The response is error, please ask administrator to help'));
        }

        return $checkoutResponse->toArray();
    }

    public function notification(): bool
    {
        $this->logger->debug(['Start to notification']);
        try {
            $authoriseMessage = $this->notificationService->processAuthoriseNotification();
        } catch (\Exception $exception) {
            $this->logger->debug([$exception->getMessage()]);

            return false;
        }

        try {
            // send confirmation to Tamara
           $response = $this->client->authoriseOrder(new AuthoriseOrderRequest($authoriseMessage->getOrderId()));

            if (!$response->isSuccess()) {
                $errorLogs = [$response->getContent()];
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
                $orderStatusCollection = $this->orderStatusCollectionFactory->create();
                $orderStatusCollection->joinStates();
                $orderStatusCollection->addFieldToFilter('main_table.status', $this->checkoutAuthoriseStatus);
                $stateWillBeUsed = Order::STATE_PROCESSING;
                foreach ($orderStatusCollection as $item) {
                    if ($item->getState() == Order::STATE_PROCESSING) {
                        $stateWillBeUsed = Order::STATE_PROCESSING;
                        break;
                    }
                    $stateWillBeUsed = $item->getState();
                }
                $mageOrder->setState($stateWillBeUsed)->setStatus($this->checkoutAuthoriseStatus);

                //set base amount paid
                $mageOrder->getPayment()->setAmountPaid($mageOrder->getGrandTotal());
                $baseAmountPaid = $mageOrder->getBaseGrandTotal();
                $mageOrder->getPayment()->setBaseAmountPaid($baseAmountPaid);
                $mageOrder->getPayment()->setBaseAmountPaidOnline($baseAmountPaid);
                $this->orderSender->send($mageOrder);

                $authorisedAmount = $mageOrder->getOrderCurrency()->formatTxt(
                    $mageOrder->getGrandTotal()
                );

                $authoriseComment = __('Tamara - order was authorised. The authorised amount is %1.', $authorisedAmount);
                $this->tamaraInvoiceHelper->log(["Create transaction after authorise payment"]);
                $this->tamaraTransactionHelper->saveAuthoriseTransaction($authoriseComment, $mageOrder, $mageOrder->getIncrementId());
                $this->mageRepository->save($mageOrder);

                if ($this->baseConfig->getAutoGenerateInvoice() == \Tamara\Checkout\Model\Config\Source\AutomaticallyInvoice::GENERATE_AFTER_AUTHORISE) {
                    $this->tamaraInvoiceHelper->log(["Automatically generate invoice after authorise payment"]);
                    $this->tamaraInvoiceHelper->generateInvoice($mageOrder->getId());
                }

                //create capture transaction
                $captureComment = __('Tamara - order was captured. The captured amount is %1.', $authorisedAmount);
                $captureTransactionId = $mageOrder->getIncrementId() . "-" . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
                $this->tamaraTransactionHelper->createTransaction($mageOrder, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, $captureComment, $captureTransactionId);
                return true;
            }

        } catch (\Exception $exception) {
            $this->logger->debug([$exception->getMessage()]);
            return false;
        }

        $this->logger->debug(['End notification']);

        return true;
    }

    public function capture(array $data, Order $order): void
    {
        $this->logger->debug(['Start to capture']);

        try {
            $captureRequest = PaymentHelper::createCaptureRequestFromArray($data);
            $response = $this->client->capture($captureRequest);

            if (!$response->isSuccess()) {
                $errorLogs = $response->getErrors() ?? [$response->getMessage()];
                $this->logger->debug($errorLogs);
                throw new IntegrationException(__('Could not capture in tamara, please check log'));
            }

            $captureId = $response->getCaptureId();
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
                $this->logger->debug(['Cannot save capture items']);
                $this->logger->debug($captureItems);
                throw new IntegrationException(__('Cannot save capture items, please check log'));
            }

            $this->mageRepository->save($order);

            if ($this->baseConfig->getAutoGenerateInvoice() == \Tamara\Checkout\Model\Config\Source\AutomaticallyInvoice::GENERATE_AFTER_CAPTURE) {
                $this->logger->debug(["Automatically generate invoice after capture payment"]);
                $this->tamaraInvoiceHelper->generateInvoice($order->getId());
            }
        } catch (\Exception $e) {
            $this->logger->debug([$e->getMessage()]);
            throw new IntegrationException(__($e->getMessage()));
        }

        $this->logger->debug(['End capture']);
    }

    public function refund(array $data): void
    {
        $this->logger->debug(['Start to refund']);

        try {
            $refundRequest = PaymentHelper::createRefundRequestFromArray($data);
            $response = $this->client->refund($refundRequest);

            if (!$response->isSuccess()) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug($errorLogs);
                throw new IntegrationException(__($response->getMessage()));
            }

            $refunds = $response->getRefunds();
            $refundIds = [];
            foreach ($refunds as $refund) {
                $refundIds[$refund->getCaptureId()] = $refund->getRefundId();
            }

            foreach ($data['refunds'] as $captureId => $refund) {
                $capture = $this->captureRepository->getCaptureById($captureId);
                $refundedAmount = $capture->getRefundedAmount() + $refund['refunded_amount'];
                $capture->setRefundedAmount($refundedAmount);
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
            $refundedAmount = $magentoOrder->getOrderCurrency()->formatTxt(
                $data['refund_grand_total']
            );
            $refundTransactionId = $magentoOrder->getIncrementId() . '-refund';
            $refundComment = __('Tamara - order was refunded. The refunded amount is %1.', $refundedAmount);
            $this->tamaraTransactionHelper->createTransaction($magentoOrder, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND, $refundComment, $refundTransactionId);
        } catch (\Exception $e) {
            $this->logger->debug([$e->getMessage()]);
            throw new IntegrationException(__($e->getMessage()));
        }

        $this->logger->debug(['End to refund']);
    }

    public function cancel(array $data): void
    {
        $this->logger->debug(['Start to cancel']);

        try {
            $cancelRequest = PaymentHelper::createCancelRequestFromArray($data);
            $response = $this->client->cancelOrder($cancelRequest);

            if (!$response->isSuccess() && $response->getStatusCode() !== 409) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug($errorLogs);
                throw new IntegrationException(__($response->getMessage()));
            }

            $cancel = PaymentHelper::createCancelFromResponse($response);
            $cancel->setOrderId($data['order_id']);
            $cancel->setRequest($cancelRequest->toArray());
            $this->cancelRepository->save($cancel);
            $mageOrder = $this->mageRepository->get($data['order_id']);
            $comment = __('Tamara - order was canceled');
            $mageOrder->addStatusHistoryComment(__($comment));
            $this->mageRepository->save($mageOrder);
        } catch (\Exception $e) {
            $this->logger->debug([$e->getMessage()]);
            throw new IntegrationException(__($e->getMessage()));
        }

        $this->logger->debug(['End to cancel']);
    }

    public function registerWebhook(): void
    {
        $this->logger->debug(['Start to register webhook']);

        try {
            $baseUrl = StoreHelper::getBaseUrl();
            $webhookUrl = $baseUrl . self::WEBHOOK_URL;

            $request = new RegisterWebhookRequest(
                $webhookUrl,
                self::ALLOWED_WEBHOOKS
            );

            $response = $this->client->registerWebhook($request);

            if (!$response->isSuccess()) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug($errorLogs);
                throw new IntegrationException(__($response->getMessage()));
            }

            $webhookId = $response->getWebhookId();

            $this->resourceConfig->saveConfig(
                'payment/tamara_checkout/webhook_id',
                $webhookId
            );
        } catch (\Exception $exception) {
            $this->logger->debug([$exception->getMessage()]);

            throw $exception;
        }

        $this->logger->debug(['End of register webhook']);
    }

    public function deleteWebhook($webhookId): void
    {
        $this->logger->debug(['Start to delete webhook']);

        $request = new RemoveWebhookRequest($webhookId);

        $response = $this->client->removeWebhook($request);

        if (!$response->isSuccess()) {
            $errorLogs = [$response->getContent()];
            $this->logger->debug($errorLogs);
            throw new IntegrationException(__($response->getMessage()));
        }

        $this->resourceConfig->deleteConfig(
            'payment/tamara_checkout/webhook_id'
        );

        $this->logger->debug(['End of delete webhook']);
    }

    public function webhook(): bool
    {
        $this->logger->debug(['Start to webhook']);

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

            /** @var \Magento\Sales\Model\Order $mageOrder */
            $mageOrder = $this->mageRepository->get($order->getOrderId());
            $mageOrder->setState(Order::STATE_CANCELED)->setStatus(Order::STATE_CANCELED);
            $comment = sprintf('Tamara - order was %s by webhook', $eventType);
            $mageOrder->addStatusHistoryComment(__($comment));
            $this->mageRepository->save($mageOrder);

        } catch (\Exception $exception) {
            $this->logger->debug([$exception->getMessage()]);
            return false;
        }

        $this->logger->debug(['End Webhook']);
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
}
