<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Tamara\Checkout\Api\OrderInterface;

class Order extends AbstractModel implements OrderInterface, IdentityInterface
{
    const CACHE_TAG = 'tamara_order';

    /**
     * @inheritDoc
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
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

    /**
     * @return bool
     */
    public function getCapturedFromConsole()
    {
        return $this->_getData('captured_from_console');
    }

    /**
     * @return bool
     */
    public function getCanceledFromConsole()
    {
        return $this->_getData('canceled_from_console');
    }

    /**
     * @return bool
     */
    public function getRefundedFromConsole()
    {
        return $this->_getData('refunded_from_console');
    }

    /**
     * @param $value bool
     * @return $this
     */
    public function setCapturedFromConsole($value)
    {
        $this->setData('captured_from_console', $value);
        return $this;
    }

    /**
     * @param $value bool
     * @return $this
     */
    public function setCanceledFromConsole($value)
    {
        $this->setData('canceled_from_console', $value);
        return $this;
    }

    /**
     * @param $value bool
     * @return $this
     */
    public function setRefundedFromConsole($value)
    {
        $this->setData('refunded_from_console', $value);
        return $this;
    }

    protected function _construct()
    {
        $this->_init(\Tamara\Checkout\Model\ResourceModel\Order::class);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentType()
    {
        return $this->getData(self::PAYMENT_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentType($value)
    {
        $this->setData(self::PAYMENT_TYPE, $value);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getNumberOfInstallments()
    {
        return $this->getData(self::NUMBER_OF_INSTALLMENTS);
    }

    /**
     * @inheritDoc
     */
    public function setNumberOfInstallments($value)
    {
        $this->setData(self::NUMBER_OF_INSTALLMENTS, $value);
        return $this;
    }
}