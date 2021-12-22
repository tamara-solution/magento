<?php

declare(strict_types=1);

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class PlaceOrder extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var \Tamara\Checkout\Model\OrderRepository
     */
    private $tamaraOrderRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        \Tamara\Checkout\Model\OrderRepository $tamaraOrderRepository
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $response = ['success' => true];
        $lastOrderId = $this->getLastOrderId();
        $logger = $this->_objectManager->get('TamaraCheckoutLogger');
        try {
            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($lastOrderId);
            $response['orderId'] = $lastOrderId;
            $response['redirectUrl'] = $tamaraOrder->getRedirectUrl();
        } catch (NoSuchEntityException $exception) {
            $response['success'] = false;
            $logger->debug(['Tamara - Error when retrieve tamara order: ' => $exception->getMessage()]);
            $response['error'] = $exception->getMessage();
        }
        return $result->setData($response);
    }

    private function getLastOrderId()
    {
        return $this->checkoutSession->getLastOrderId();
    }
}
