<?php

namespace Tamara\Checkout\Controller\Adminhtml\Whitelist;

use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends \Magento\Backend\App\Action
{

    protected $resultPageFactory;

    protected $filter;

    protected $_registry;

    protected $collectionFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry,
        \Tamara\Checkout\Model\ResourceModel\EmailWhiteList\CollectionFactory $collectionFactory,
        Filter $filter
    ) {
        $this->filter = $filter;
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->_registry = $registry;
        $this->collectionFactory =$collectionFactory;
    }


    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();
        try {
            foreach ($collection as $item) {
                $item->delete();
            }
        }catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
        }

        $this->messageManager->addSuccess(
            __('A total of %1 record(s) have been deleted.', $collectionSize)
        );

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
