<?php

namespace Tamara\Checkout\Controller\Adminhtml\Whitelist;

class Import extends \Magento\Backend\App\Action
{

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Registry
     */

    protected $_registry;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->_registry = $registry;
    }
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        $resultPage->addBreadcrumb(__('Whitelist Email Manager'), __('Whitelist Email Manager'))
            ->addBreadcrumb(__('Import Whitelist Email'), __('Import Whitelist Email'))
            ->setActiveMenu('Tamara_Checkout::tamara_whitelist');

        $resultPage->getConfig()->getTitle()->prepend(__('Import Whitelist Email'));

        return $resultPage;
    }
}
