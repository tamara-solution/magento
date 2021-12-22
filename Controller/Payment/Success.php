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
        try {
            $orderId = $this->_request->getParam('order_id', 0);

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
            $storeId = $order->getStoreId();
            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);
            $isAllowed = false;
            $magentoOrderState = $order->getState();
            if ($magentoOrderState == \Magento\Sales\Model\Order::STATE_NEW) {
                $isAllowed = true;
            }
            if ($magentoOrderState == \Magento\Sales\Model\Order::STATE_PROCESSING) {
                if ($tamaraOrder->getIsAuthorised()) {
                    $isAllowed = true;
                }
            }
            if (!$isAllowed) {
                return $this->redirectToCartPage();
            }
        } catch (\Exception $exception) {
            return $this->redirectToCartPage();
        }
        try {
            if (!(bool) $tamaraOrder->getIsAuthorised()) {
                $successStatus = $this->config->getCheckoutSuccessStatus($storeId);
                $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus($successStatus);
                $order->addCommentToStatusHistory(__('Tamara - order checkout success, we will confirm soon'));
                $order->getResource()->save($order);
            }
        } catch (\Exception $e) {
            $logger->debug(['Tamara - Success has error' => $e->getMessage()]);
        }

        if ($this->config->useMagentoCheckoutSuccessPage($storeId)) {
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success/');
        }

        //dispatch event onepage
        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$orderId],
                'order' => $order
            ]
        );

        $quoteId = $this->checkoutSession->getQuoteId();
        if ($quoteId) {
            $this->cartHelper->removeCartAfterSuccess($quoteId);
        }

        if (!empty($merchantSuccessUrl = $this->config->getMerchantSuccessUrl($storeId))) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($merchantSuccessUrl);
            return $resultRedirect;
        }

        $page = $this->_pageFactory->create();
        $block = $page->getLayout()->getBlock('tamara_success');
        $block->setData('order_id', $orderId);
        $block->setData('order_increment_id', $order->getIncrementId());
        return $page;
    }

    public function redirectToCartPage() {
        $this->_redirect('checkout/cart');
        return $this->getResponse()->sendResponse();
    }
}