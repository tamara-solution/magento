<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Tamara\Checkout\Api\CaptureRepositoryInterface;

class CaptureRepository implements CaptureRepositoryInterface
{
    /**
     * @var \Tamara\Checkout\Model\CaptureFactory
     */
    protected $captureFactory;

    /**
     * @var \Tamara\Checkout\Model\CaptureItemFactory
     */
    protected $captureItemFactory;

    /**
     * @var \Tamara\Checkout\Model\ResourceModel\Capture
     */
    protected $captureResource;

    /**
     * @var \Tamara\Checkout\Model\ResourceModel\CaptureItem
     */
    protected $captureItemResource;

    /**
     * CaptureRepository constructor.
     * @param CaptureFactory $captureFactory
     * @param CaptureItemFactory $captureItemFactory
     * @param ResourceModel\Capture $captureResource
     * @param ResourceModel\CaptureItem $captureItemResource
     */
    public function __construct(
        CaptureFactory $captureFactory,
        CaptureItemFactory $captureItemFactory,
        ResourceModel\Capture $captureResource,
        ResourceModel\CaptureItem $captureItemResource
    )
    {
        $this->captureFactory = $captureFactory;
        $this->captureItemFactory = $captureItemFactory;
        $this->captureResource = $captureResource;
        $this->captureItemResource = $captureItemResource;
    }

    public function getCaptureByConditions(array $conditions)
    {
        try {
            $result = $this->captureResource->getByConditions($conditions);
        } catch (\Exception $e) {
            throw new NotFoundException(__($e->getMessage()));
        }

        return $result;
    }

    public function getCaptureItemsByConditions(array $conditions)
    {
        try {
            $result = $this->captureItemResource->getByConditions($conditions);
        } catch (\Exception $e) {
            throw new NotFoundException(__($e->getMessage()));
        }

        return $result;
    }

    public function saveCapture(Capture $capture)
    {
        try {
            $this->captureResource->save($capture);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }

    public function saveCaptureItems(array $data)
    {
        try {
            $connection = $this->captureItemResource->getConnection();
            return $connection->insertMultiple($this->captureItemResource->getMainTable(), $data);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
    }

    public function getCaptureById($captureId)
    {
        $capture = $this->captureFactory->create();
        $id = $this->captureResource->getByCaptureId($captureId);
        if (!$id) {
            throw new NoSuchEntityException(__('Requested order doesn\'t exist: ' . $id));
        }

        $this->captureResource->load($capture, $id);

        return $capture;
    }
}