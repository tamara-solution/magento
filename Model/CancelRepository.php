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

    public function __construct(ResourceModel\Cancel $resourceModel)
    {
        $this->resourceModel = $resourceModel;
    }

    public function save(Cancel $cancel)
    {
        try {
            $this->resourceModel->save($cancel);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }
}