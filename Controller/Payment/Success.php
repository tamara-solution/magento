<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Tamara\Checkout\Model\Helper\CartHelper;

class Success extends Action
{
    protected $_pageFactory;

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
        Session $checkoutSession
    ) {
        $this->_pageFactory = $pageFactory;
        parent::__construct($context);
        $this->cartHelper = $cartHelper;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $orderId = $this->_request->getParam('order_id', 0);
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