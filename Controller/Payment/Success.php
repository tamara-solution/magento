<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ResponseInterface;

class Success extends Action
{
    protected $_pageFactory;

    /**
     * Success constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    )
    {
        $this->_pageFactory = $pageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $orderId = $this->_request->getParam('order_id', 0);
        $page = $this->_pageFactory->create();

        $block = $page->getLayout()->getBlock('tamara_success');
        $block->setData('order_id', $orderId);

        return $page;
    }
}