<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Tamara\Checkout\Api\RefundRepositoryInterface;
use Tamara\Checkout\Model\ResourceModel\Refund as RefundResource;

class RefundRepository implements RefundRepositoryInterface
{
    /**
     * @var RefundResource
     */
    private $resourceModel;

    protected $refundCollectionFactory;

    /**
     * RefundRepository constructor.
     * @param RefundResource $resourceModel
     */
    public function __construct(RefundResource $resourceModel,
        \Tamara\Checkout\Model\ResourceModel\Refund\CollectionFactory $refundCollectionFactory
    )
    {
        $this->resourceModel = $resourceModel;
        $this->refundCollectionFactory = $refundCollectionFactory;
    }

    public function save(Refund $refund)
    {
        try {
            $this->resourceModel->save($refund);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getRefundsByOrderId($orderId) {
        $refundCollection = $this->refundCollectionFactory->create()
            ->addFieldToFilter('order_id', $orderId)
            ->load();
        return $refundCollection->getItems();
    }
}