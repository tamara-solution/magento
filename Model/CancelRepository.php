<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Tamara\Checkout\Api\CancelRepositoryInterface;

class CancelRepository implements CancelRepositoryInterface
{
    /**
     * @var \Tamara\Checkout\Model\ResourceModel\Cancel
     */
    private $resourceModel;

    protected $cancelCollectionFactory;

    public function __construct(ResourceModel\Cancel $resourceModel,
        \Tamara\Checkout\Model\ResourceModel\Cancel\CollectionFactory $cancelCollectionFactory
    )
    {
        $this->resourceModel = $resourceModel;
        $this->cancelCollectionFactory = $cancelCollectionFactory;
    }

    public function save(Cancel $cancel)
    {
        try {
            $this->resourceModel->save($cancel);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getCancelsByOrderId($orderId)
    {
        $cancelCollection = $this->cancelCollectionFactory->create()
            ->addFieldToFilter('order_id', $orderId)
            ->load();
        return $cancelCollection->getItems();
    }
}