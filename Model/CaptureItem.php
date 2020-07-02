<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\Model\AbstractModel;

class CaptureItem extends AbstractModel
{
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
    public function getOrderItemId()
    {
        return $this->_getData('order_item_id');
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderItemId($orderItemId)
    {
        $this->setData('order_item_id', $orderItemId);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->_getData('name');
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->setData('name', $name);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getImageUrl()
    {
        return $this->_getData('image_url');
    }

    /**
     * {@inheritdoc}
     */
    public function setImageUrl($imageUrl)
    {
        $this->setData('image_url', $imageUrl);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSku()
    {
        return $this->_getData('sku');
    }

    /**
     * {@inheritdoc}
     */
    public function setSku($sku)
    {
        $this->setData('sku', $sku);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->_getData('type');
    }

    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        $this->setData('type', $type);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuantity()
    {
        return $this->_getData('quantity');
    }

    /**
     * {@inheritdoc}
     */
    public function setQuantity($quantity)
    {
        $this->setData('quantity', $quantity);
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
    public function getUnitPrice()
    {
        return $this->_getData('unit_price');
    }

    /**
     * {@inheritdoc}
     */
    public function setUnitPrice($unitPrice)
    {
        $this->setData('unit_price', $unitPrice);
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