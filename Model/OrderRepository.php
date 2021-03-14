<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tamara\Checkout\Api\OrderInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Model\ResourceModel\Order as OrderResource;

class OrderRepository implements OrderRepositoryInterface
{
    /**
     * @var OrderResource
     */
    private $resourceModel;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * OrderRepository constructor.
     * @param OrderResource $resourceModel
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        OrderResource $resourceModel,
        \Tamara\Checkout\Model\OrderFactory $orderFactory
    )
    {
        $this->resourceModel = $resourceModel;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(OrderInterface $order)
    {
        try {
            $this->resourceModel->save($order);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }
        return $order;
    }

    /**
     * @inheritDoc
     */
    public function getTamaraOrderByOrderId($magentoOrderId)
    {
        $order = $this->orderFactory->create();
        $orderId = $this->resourceModel->getByOrderId($magentoOrderId);
        if (!$orderId) {
            throw new NoSuchEntityException(__('Requested order doesn\'t exist: ' . $magentoOrderId));
        }
        $this->resourceModel->load($order, $orderId);
        return $order;
    }

    /**
     * @inheritDoc
     */
    public function getTamaraOrderByTamaraOrderId($tamaraOrderId)
    {
        $order = $this->orderFactory->create();
        $orderId = $this->resourceModel->getByTamaraOrderId($tamaraOrderId);
        if (!$orderId) {
            throw new NoSuchEntityException(__('Requested order doesn\'t exist: ' . $tamaraOrderId));
        }
        $this->resourceModel->load($order, $orderId);
        return $order;
    }
}