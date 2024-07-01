<?php

namespace Tamara\Checkout\Model;
use Magento\Framework\Model\AbstractModel;

class Cancel extends AbstractModel
{
    const CACHE_TAG = 'tamara_cancels';

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    protected function _construct()
    {
        $this->_init(\Tamara\Checkout\Model\ResourceModel\Cancel::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelId()
    {
        return $this->_getData('cancel_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setCancelId($cancelId)
    {
        $this->setData('cancel_id', $cancelId);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderId()
    {
        return $this->_getData('order_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderId($orderId)
    {
        $this->setData('order_id', $orderId);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTamaraOrderId()
    {
        return $this->_getData('tamara_order_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setTamaraOrderId($id)
    {
        $this->setData('tamara_order_id', $id);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequest()
    {
        $data = $this->_getData('request');
        return json_decode($data, true);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequest($request)
    {
        $data = [];
        if (!empty($request)) {
            $data = json_encode($request);
        }
        $this->setData('request', $data);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->_getData('created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt()
    {
        return $this->_getData('updated_at');
    }
}
