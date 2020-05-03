<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tamara\Checkout\Api\OrderInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Model\ResouceModel\Order as OrderResource;

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
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws CouldNotSaveException
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
     * @param $id
     * @return \Magento\Sales\Model\Order|Order
     * @throws NoSuchEntityException
     */
    public function getTamaraOrderByOrderId($id)
    {
        $order = $this->orderFactory->create();
        $orderId = $this->resourceModel->getByOrderId($id);
        if (!$orderId) {
            throw new NoSuchEntityException(__('Requested order doesn\'t exist: ' . $orderId));
        }
        $this->resourceModel->load($order, $orderId);
        return $order;
    }
}