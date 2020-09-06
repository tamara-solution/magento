<?php

declare(strict_types=1);

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;
use Exception;
use Tamara\Checkout\Model\OrderFactory;

class IframeCheckout extends Action
{
    private const TAMARA_LOGGER = 'TamaraCheckoutLogger';
    protected $_pageFactory;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var \Tamara\Checkout\Model\OrderRepository
     */
    private $tamaraOrderRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $_pageFactory,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        QuoteManagement $quoteManagement,
        \Tamara\Checkout\Model\OrderRepository $tamaraOrderRepository
    ) {
        $this->_pageFactory = $_pageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteManagement = $quoteManagement;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $paymentMethod = $this->_request->getParam('payment_method', null);

        $logger = $this->_objectManager->get(self::TAMARA_LOGGER);
        $quote = $this->checkoutSession->getQuote();
        $quote->getPayment()->importData(['method' => $paymentMethod]);
        $result = $this->resultJsonFactory->create();

        try {
            $orderQuote = $this->quoteManagement->submit($quote);
        } catch (Exception $e) {
            $logger->debug(['Error when convert from quote to order' => $e->getMessage()]);
            return $result->setData(['error' => 'Cannot get order from session']);
        }

        if (!$orderQuote instanceof Order) {
            $logger->debug(['Order was invalid: ' => $orderQuote->toArray()]);
            return $result->setData(['error' => 'Order was invalid']);
        }

        try {
            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderQuote->getEntityId());
        } catch (NoSuchEntityException $e) {
            $logger->debug(['Cannot find Tamara order with id : ' => $orderQuote->getId()]);
            return $result->setData(['error' => 'Cannot find order']);
        }

        $response['orderId'] = $orderQuote->getEntityId();
        $response['redirectUrl'] = $tamaraOrder->getRedirectUrl();
        $response['success'] = true;
        return $result->setData($response);
    }
}
