<?php

namespace Tamara\Checkout\Api\Data;

interface CheckoutInformationInterface
{

    const TAMARA_ORDER_ID = 'tamara_order_id';
    const MAGENTO_ORDER_ID = 'magento_order_id';
    const SUCCESS_REDIRECT_URL = 'checkout_sucess_url';
    const CANCEL_REDIRECT_URL = 'checkout_cancel_url';
    const FAILURE_REDIRECT_URL = 'checkout_failure_url';
    const REDIRECT_URL = 'checkout_redirect_url';
    const SANDBOX_API_URL = "https://api-sandbox.tamara.co";
    const SANDBOX_API_ENVIRONMENT = "1";
    const PRODUCTION_API_URL = "https://api.tamara.co";
    const PRODUCTION_API_ENVIRONMENT = "2";


    /**
     * @param string $tamaraOrderId
     * @return $this
     */
    public function setTamaraOrderId($tamaraOrderId);

    /**
     * @return string
     */
    public function getTamaraOrderId();

    /**
     * @param int $magentoOrderId
     * @return $this
     */
    public function setMagentoOrderId($magentoOrderId);

    /**
     * @return int
     */
    public function getMagentoOrderId();

    /**
     * @param string $successUrl
     * @return $this
     */
    public function setPaymentSuccessRedirectUrl($successUrl);

    /**
     * @return string
     */
    public function getPaymentSuccessRedirectUrl();

    /**
     * @param string $cancelUrl
     * @return $this
     */
    public function setPaymentCancelRedirectUrl($cancelUrl);

    /**
     * @return string
     */
    public function getPaymentCancelRedirectUrl();

    /**
     * @param string $failureUrl
     * @return $this
     */
    public function setPaymentFailureRedirectUrl($failureUrl);

    /**
     * @return string
     */
    public function getPaymentFailureRedirectUrl();

    /**
     * Set checkout redirect url
     * @param string $redirectUrl
     * @return $this
     */
    public function setRedirectUrl($redirectUrl);

    /**
     * @return string
     */
    public function getRedirectUrl();

}