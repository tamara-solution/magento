<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Model\AbstractModel;

class Refund extends AbstractModel
{
    const CACHE_TAG = 'tamara_refunds';

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    protected function _construct()
    {
        $this->_init(\Tamara\Checkout\Model\ResourceModel\Refund::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getRefundId()
    {
        return $this->_getData('refund_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setRefundId($refundId)
    {
        $this->setData('refund_id', $refundId);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureId()
    {
        return $this->_getData('capture_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setCaptureId($captureId)
    {
        $this->setData('capture_id', $captureId);
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
    public function getTotalAmount()
    {
        return $this->_getData('total_amount');
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalAmount($totalAmount)
    {
        $this->setData('total_amount', $totalAmount);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefundedAmount()
    {
        return $this->_getData('refunded_amount');
    }

    /**
     * {@inheritdoc}
     */
    public function setRefundedAmount($refundedAmount)
    {
        $this->setData('refunded_amount', $refundedAmount);
        return $this;
    }


    /**
     * {@inheritdoc}
     */
    public function getCurrency()
    {
        return $this->_getData('currency');
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrency($currency)
    {
        $this->setData('currency', $currency);
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
