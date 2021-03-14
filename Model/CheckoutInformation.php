<?php

namespace Tamara\Checkout\Model;

use Tamara\Checkout\Api\Data\CheckoutInformationInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class CheckoutInformation extends AbstractModel implements CheckoutInformationInterface, IdentityInterface
{

    /**
     * Checkout information cache tag
     */
    const CACHE_TAG = 'checkout_information';

    protected $_cacheTag = self::CACHE_TAG;

    /**
     * Return unique ID(s) for each object in system
     *
     * @return string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getId()
    {
        return $this->getTamaraOrderId();
    }

    /**
     * @return string
     */
    public function getTamaraOrderId()
    {
        return $this->getData(self::TAMARA_ORDER_ID);
    }

    /**
     * @param string $tamaraOrderId
     * @return $this
     */
    public function setTamaraOrderId($tamaraOrderId)
    {
        $this->setData(self::TAMARA_ORDER_ID, $tamaraOrderId);
        return $this;
    }

    /**
     * @param int $magentoOrderId
     * @return $this
     */
    public function setMagentoOrderId($magentoOrderId)
    {
        $this->setData(self::MAGENTO_ORDER_ID, $magentoOrderId);
        return $this;
    }

    /**
     * @return int
     */
    public function getMagentoOrderId()
    {
        return $this->getData(self::MAGENTO_ORDER_ID);
    }

    /**
     * @param string $successUrl
     * @return $this
     */
    public function setPaymentSuccessRedirectUrl($successUrl)
    {
        $this->setData(self::SUCCESS_REDIRECT_URL, $successUrl);
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentSuccessRedirectUrl()
    {
        return $this->getData(self::SUCCESS_REDIRECT_URL);
    }

    /**
     * @param string $cancelUrl
     * @return $this
     */
    public function setPaymentCancelRedirectUrl($cancelUrl)
    {
        $this->setData(self::CANCEL_REDIRECT_URL, $cancelUrl);
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentCancelRedirectUrl()
    {
        return $this->getData(self::CANCEL_REDIRECT_URL);
    }

    /**
     * @param string $failureUrl
     * @return $this
     */
    public function setPaymentFailureRedirectUrl($failureUrl)
    {
        $this->setData(self::FAILURE_REDIRECT_URL, $failureUrl);
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentFailureRedirectUrl()
    {
        return $this->getData(self::FAILURE_REDIRECT_URL);
    }

    /**
     * Set checkout redirect url
     * @param string $redirectUrl
     * @return $this
     */
    public function setRedirectUrl($redirectUrl)
    {
        $this->setData(self::REDIRECT_URL, $redirectUrl);
        return $this;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->getData(self::REDIRECT_URL);
    }
}