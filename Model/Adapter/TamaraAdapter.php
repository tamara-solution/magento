<?php

namespace Tamara\Checkout\Model\Adapter;

use Magento\Framework\Exception\IntegrationException;
use Magento\Payment\Model\Method\Logger;
use Magento\Setup\Exception;
use Tamara\Checkout\Api\CancelRepositoryInterface;
use Tamara\Checkout\Api\CaptureRepositoryInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Api\RefundRepositoryInterface;
use Tamara\Checkout\Model\Helper\OrderHelper;
use Tamara\Checkout\Model\Helper\PaymentHelper;
use Tamara\Client;
use Tamara\Configuration;
use Tamara\Exception\RequestDispatcherException;
use Tamara\Model\Checkout\PaymentType;
use Tamara\Notification\NotificationService;
use Tamara\Request\Checkout\CreateCheckoutRequest;
use Tamara\Request\Order\AuthoriseOrderRequest;

class TamaraAdapter
{
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
    private $checkoutSuccessStatus;

    public function __construct(
        $apiUrl,
        $merchantToken,
        $notificationToken,
        $checkoutSuccessStatus,
        $orderRepository,
        $captureRepository,
        $mageRepository,
        $refundRepository,
        $cancelRepository,
        $logger
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
        $this->checkoutSuccessStatus = $checkoutSuccessStatus;
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
        $orderRequest = OrderHelper::createTamaraOrderFromArray($data);

        try  {
            $result = $this->client->createCheckout(new CreateCheckoutRequest($orderRequest));
        } catch (RequestDispatcherException $e) {
            $this->logger->debug([$e->getMessage()]);
            throw $e;
        }

        if (!$result->isSuccess()) {
            $errorLogs = [$result->getContent()];
            $this->logger->debug($errorLogs);
            throw new IntegrationException(__($result->getMessage()));
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

            $orderId = $authoriseMessage->getOrderReferenceId();
            $order = $this->orderRepository->getTamaraOrderByOrderId((int) $orderId);
            $order->setIsAuthorised(1);
            $this->orderRepository->save($order);

            if (!empty($this->checkoutSuccessStatus)) {
                $mageOrder = $this->mageRepository->get((int) $orderId);
                $mageOrder->setStatus($this->checkoutSuccessStatus)->setState($this->checkoutSuccessStatus);
                $this->mageRepository->save($mageOrder);
            }

        } catch (\Exception $exception) {
            $this->logger->debug([$exception->getMessage()]);
            return false;
        }

        $this->logger->debug(['End notification']);

        return true;
    }

    public function capture(array $data): void
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

            if (!$response->isSuccess()) {
                $errorLogs = [$response->getContent()];
                $this->logger->debug($errorLogs);
                throw new IntegrationException(__($response->getMessage()));
            }

            $cancel = PaymentHelper::createCancelFromResponse($response);
            $cancel->setOrderId($data['order_id']);
            $cancel->setRequest($cancelRequest->toArray());
            $this->cancelRepository->save($cancel);

        } catch (\Exception $e) {
            $this->logger->debug([$e->getMessage()]);
            throw new IntegrationException(__($e->getMessage()));
        }

        $this->logger->debug(['End to cancel']);
    }
}