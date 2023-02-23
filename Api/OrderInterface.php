<?php

namespace Tamara\Checkout\Api;

interface OrderInterface
{
    const PAYMENT_TYPE = 'payment_type';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';

    /**
     * @return int
     */
    public function getTamaraId();

    /**
     * Get Magento Order ID
     *
     * @return int
     */
    public function getOrderId();

    /**
     * Set the Magento Order ID
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * @return string
     */
    public function getTamaraOrderId();

    /**
     * @param string $id
     * @return $this
     */
    public function setTamaraOrderId($id);

    /**
     * @return string
     */
    public function getRedirectUrl();

    /**
     * @param string $url
     * @return $this
     */
    public function setRedirectUrl($url);

    /**
     * @return int
     */
    public function getIsAuthorised();

    /**
     * @param int $isAuthorised
     * @return $this
     */
    public function setIsAuthorised($isAuthorised);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @return bool
     */
    public function getCapturedFromConsole();

    /**
     * @return bool
     */
    public function getCanceledFromConsole();

    /**
     * @return bool
     */
    public function getRefundedFromConsole();

    /**
     * @param $value bool
     * @return $this
     */
    public function setCapturedFromConsole($value);

    /**
     * @param $value bool
     * @return $this
     */
    public function setCanceledFromConsole($value);

    /**
     * @param $value bool
     * @return $this
     */
    public function setRefundedFromConsole($value);

    /**
     * @return string
     */
    public function getPaymentType();

    /**
     * @param string $value
     * @return $this
     */
    public function setPaymentType($value);

    /**
     * @return int
     */
    public function getNumberOfInstallments();

    /**
     * @param int $value
     * @return $this
     */
    public function setNumberOfInstallments($value);
}