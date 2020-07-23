<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\CartHelper;
use Tamara\Checkout\Api\OrderRepositoryInterface as TamaraOrderRepository;

class Success extends Action
{
    protected $_pageFactory;

    /**
     * @var CartHelper;
     */
    private $cartHelper;

    protected $orderRepository;

    protected $config;

    protected $tamaraOrderRepository;

    /**
     * @var Session
     */
    private $checkoutSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        CartHelper $cartHelper,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config,
        Session $checkoutSession,
        TamaraOrderRepository $tamaraOrderRepository
    ) {
        $this->_pageFactory = $pageFactory;
        parent::__construct($context);
        $this->cartHelper = $cartHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
    }

    public function execute()
    {
        $logger = $this->_objectManager->get('TamaraCheckoutLogger');
        try {
            $orderId = $this->_request->getParam('order_id', 0);
            $successStatus = $this->config->getCheckoutSuccessStatus();

            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);

            if (!$tamaraOrder->getIsAuthorised()) {
                /** @var \Magento\Sales\Model\Order $order */
                $order = $this->orderRepository->get($orderId);
                $order->setState(Order::STATE_PROCESSING)->setStatus($successStatus);
                $order->addCommentToStatusHistory(__('Tamara - order was processing'));
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $logger->debug(['Success has error' => $e->getMessage()]);
        }

        $page = $this->_pageFactory->create();

        $block = $page->getLayout()->getBlock('tamara_success');
        $block->setData('order_id', $orderId);

        $quoteId = $this->checkoutSession->getQuoteId();

        if ($quoteId === null) {
            return $page;
        }

        $this->cartHelper->removeCartAfterSuccess($quoteId);

        return $page;
    }
}