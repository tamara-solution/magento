<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Api\OrderRepositoryInterface as TamaraOrderRepository;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\CartHelper;

class Success extends Action
{
    protected $_pageFactory;
    protected $orderRepository;
    protected $config;
    protected $tamaraOrderRepository;
    /**
     * @var CartHelper;
     */
    private $cartHelper;
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
        if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        $orderId = $this->checkoutSession->getLastOrderId();

        //dispatch event onepage
        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$orderId],
                'order' => $this->checkoutSession->getLastRealOrder()
            ]
        );

        try {
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