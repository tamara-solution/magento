<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Model\AbstractModel;

class Capture extends AbstractModel
{
    public const
        EMPTY_STRING = 'N/A';

    const CACHE_TAG = 'tamara_captures';

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    protected function _construct()
    {
        $this->_init(\Tamara\Checkout\Model\ResourceModel\Capture::class);
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
    public function getTaxAmount()
    {
        return $this->_getData('tax_amount');
    }

    /**
     * {@inheritdoc}
     */
    public function setTaxAmount($taxAmount)
    {
        $this->setData('tax_amount', $taxAmount);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getShippingAmount()
    {
        return $this->_getData('shipping_amount');
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingAmount($shippingAmount)
    {
        $this->setData('shipping_amount', $shippingAmount);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDiscountAmount()
    {
        return $this->_getData('discount_amount');
    }

    /**
     * {@inheritdoc}
     */
    public function setDiscountAmount($discountAmount)
    {
        $this->setData('discount_amount', $discountAmount);
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
    public function getShippingInfo()
    {
        $data = $this->_getData('shipping_info');

        if ($data !== self::EMPTY_STRING) {
            $data = json_decode($data, true);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingInfo($shippingInfo)
    {
        $data = self::EMPTY_STRING;
        if (!empty($shippingInfo) && $shippingInfo !== self::EMPTY_STRING) {
            $data = json_encode($shippingInfo);
        }
        $this->setData('shipping_info', $data);
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
