<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\CartHelper;

class Failure extends Action
{
    protected $pageFactory;

    protected $cartHelper;

    protected $orderRepository;

    protected $config;

    /**
     * Cancel constructor.
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param CartHelper $cartHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param BaseConfig $config
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        CartHelper $cartHelper,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config
    ) {
        $this->pageFactory = $pageFactory;
        $this->cartHelper = $cartHelper;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $orderId = $this->_request->getParam('order_id', 0);

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
            $order->setState(Order::STATE_CLOSED)->setStatus($this->config->getCheckoutFailureStatus());
            $order->addCommentToStatusHistory(__('Tamara - order was failure'));
            $this->orderRepository->save($order);
            $this->cartHelper->restoreCartFromOrder($order);

        } catch (\Exception $e) {

        }
        $page = $this->pageFactory->create();
        $block = $page->getLayout()->getBlock('tamara_failure');
        $block->setData('checkout_url', sprintf('%s%s', $this->_url->getBaseUrl(), 'checkout/cart'));

        return $page;
    }
}