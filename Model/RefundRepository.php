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

    /**
     * RefundRepository constructor.
     * @param RefundResource $resourceModel
     */
    public function __construct(RefundResource $resourceModel)
    {
        $this->resourceModel = $resourceModel;
    }

    public function save(Refund $refund)
    {
        try {
            $this->resourceModel->save($refund);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }
}