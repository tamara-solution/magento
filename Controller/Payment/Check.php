<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Tamara\Checkout\Model\OrderRepository;

class Check extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * Check constructor.
     * @param Context $context
     * @param OrderRepository $orderRepository
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        OrderRepository $orderRepository,
        JsonFactory $resultJsonFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $orderId = $this->_request->getParam('order_id', 0);
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        try {
            $order = $this->orderRepository->getTamaraOrderByOrderId($orderId);
        } catch (NoSuchEntityException $e) {
            $response = ['message' => $e->getMessage(), 'order_id' => $orderId];
            $resultJson->setData($response);
            return $resultJson;
        }

        $response = ['success' => $order->getIsAuthorised() ? true : false];

        $resultJson->setData($response);

        return $resultJson;
    }
}
