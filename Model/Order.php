<?php

namespace Tamara\Checkout\Model;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Tamara\Checkout\Api\OrderInterface;

class Order extends AbstractModel implements OrderInterface, IdentityInterface
{
    const CACHE_TAG = 'tamara_order';

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    protected function _construct()
    {
        $this->_init(\Tamara\Checkout\Model\ResouceModel\Order::class);
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
    public function getRedirectUrl()
    {
        return $this->_getData('redirect_url');
    }

    /**
     * {@inheritdoc}
     */
    public function setRedirectUrl($url)
    {
        $this->setData('redirect_url', $url);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIsAuthorised()
    {
        return $this->_getData('is_authorised');
    }

    /**
     * {@inheritdoc}
     */
    public function setIsAuthorised($isAuthorised)
    {
        $this->setData('is_authorised', $isAuthorised);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTamaraId()
    {
        return $this->_getData('tamara_id');
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