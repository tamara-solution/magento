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

class Cancel extends Action
{
    protected $pageFactory;

    protected $cartHelper;

    protected $orderRepository;

    protected $config;

    /**
     * @var Session
     */
    private $checkoutSession;

    private $orderManagement;

    private $coreRegistry;

    private $tamaraOrderRepository;

    private $tamaraAdapterFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        CartHelper $cartHelper,
        OrderRepositoryInterface $orderRepository,
        Session $checkoutSession,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Framework\Registry $coreRegistry,
        BaseConfig $config,
        TamaraOrderRepository $tamaraOrderRepository,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->cartHelper = $cartHelper;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->orderManagement = $orderManagement;
        $this->coreRegistry = $coreRegistry;
        $this->config = $config;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $orderId = $this->_request->getParam("order_id", 0);

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
            if ($order->getState() != \Magento\Sales\Model\Order::STATE_NEW) {
                throw new \Exception("Order status does not support");
            }
            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);
            if ((bool) $tamaraOrder->getIsAuthorised()) {
                throw new \Exception("Order was authorized");
            } else {
                $tamaraAdapter = $this->tamaraAdapterFactory->create();
                if ($tamaraAdapter->getTamaraOrderFromRemote($order->getIncrementId())->getStatus() == "approved") {
                    throw new \Exception("Order was approved");
                }
            }
        } catch (\Exception $exception) {
            $this->_redirect('checkout/cart');
            return $this->getResponse()->sendResponse();
        }
        try {
            $this->cartHelper->restoreCartFromOrder($order);
            $this->coreRegistry->register("skip_tamara_cancel", true);
            $this->orderManagement->cancel($orderId);
            $order->setState(Order::STATE_CANCELED)->setStatus($this->config->getCheckoutCancelStatus());
            $order->addStatusHistoryComment(__('Tamara - order was canceled'));
            $order->getResource()->save($order);
        } catch (\Exception $e) {
            $logger = $this->_objectManager->get('TamaraCheckoutLogger');
            $logger->debug(["Error when process payment cancel: " . $e->getMessage()]);
        }

        $message = __('Your order was cancelled.');
        $this->messageManager->addErrorMessage($message);

        $this->_redirect('checkout/cart');
        $this->getResponse()->sendResponse();
    }
}
