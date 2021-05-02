<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\CartHelper;
use Tamara\Checkout\Api\OrderRepositoryInterface as TamaraOrderRepository;

class Failure extends Action
{
    protected $pageFactory;

    protected $cartHelper;

    protected $orderRepository;

    protected $config;

    /**
     * @var Session
     */
    private $checkoutSession;

    private $tamaraOrderRepository;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        CartHelper $cartHelper,
        OrderRepositoryInterface $orderRepository,
        Session $checkoutSession,
        BaseConfig $config,
        TamaraOrderRepository $tamaraOrderRepository
    ) {
        $this->pageFactory = $pageFactory;
        $this->cartHelper = $cartHelper;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            $magentoOrder = $this->checkoutSession->getLastRealOrder();
            if (empty($orderId) || empty($magentoOrder->getGrandTotal())) {
                $this->_redirect('checkout/cart');
                return $this->getResponse()->sendResponse();
            }

            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);

            if ((bool) $tamaraOrder->getIsAuthorised()) {
                $this->_redirect('checkout/cart');
                return $this->getResponse()->sendResponse();
            }

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
            $order->setState(Order::STATE_CLOSED)->setStatus($this->config->getCheckoutFailureStatus());
            $order->addStatusHistoryComment(__('Tamara - order was failure'));
            $this->orderRepository->save($order);

            $this->cartHelper->restoreCartFromOrder($order);

        } catch (\Exception $e) {
        }

        $message = __('Your order was failed.');
        $this->messageManager->addErrorMessage($message);

        $this->_redirect('checkout/cart');
        $this->getResponse()->sendResponse();
    }
}
