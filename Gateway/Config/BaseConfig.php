<?php
namespace Tamara\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\Config as MagentoPaymentConfig;

class BaseConfig extends MagentoPaymentConfig
{
    const CODE = 'tamara_checkout';

    const MERCHANT_TOKEN = 'merchant_token';
    const NOTIFICATION_TOKEN = 'notification_token';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     * @param null|string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $serializer,
        $methodCode = self::CODE,
        $pathPattern = MagentoPaymentConfig::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->serializer = $serializer;
    }

    public function getMerchantToken($storeId = null) {
        return $this->getValue(self::MERCHANT_TOKEN, $storeId);
    }

    public function getNotificationToken($storeId = null) {
        return $this->getValue(self::NOTIFICATION_TOKEN, $storeId);
    }

    public function getApiUrl($storeId = null)
    {
        return $this->getValue('api_url', $storeId);
    }

    public function getMerchantSuccessUrl($storeId = null)
    {
        return $this->getValue('merchant_success_url', $storeId);
    }

    public function getMerchantFailureUrl($storeId = null)
    {
        return $this->getValue('merchant_failure_url', $storeId);
    }

    public function getMerchantCancelUrl($storeId = null)
    {
        return $this->getValue('merchant_cancel_url', $storeId);
    }

    public function getMerchantNotificationUrl($storeId = null)
    {
        return $this->getValue('merchant_notification_url', $storeId);
    }

    public function getCheckoutCancelStatus($storeId = null)
    {
        return $this->getValue('checkout_order_statuses/checkout_cancel_status', $storeId);
    }

    public function getCheckoutFailureStatus($storeId = null)
    {
        return $this->getValue('checkout_order_statuses/checkout_failure_status', $storeId);
    }

    public function getCheckoutSuccessStatus($storeId = null)
    {
        return $this->getValue('checkout_order_statuses/checkout_success_status', $storeId);
    }

    public function getCheckoutAuthoriseStatus($storeId = null)
    {
        return $this->getValue('checkout_order_statuses/checkout_authorise_status', $storeId);
    }

    public function getTriggerActions($storeId = null)
    {
        return $this->getValue('trigger_actions', $storeId);
    }

    public function getSendEmailInvoice($storeId = null)
    {
        return $this->getValue('send_email_invoice', $storeId);
    }

    public function getLinkAboutTamara($storeId = null)
    {
        return $this->getValue('link_about_tamara', $storeId);
    }

    public function getLinkLoginTamara($storeId = null)
    {
        return $this->getValue('link_login_tamara', $storeId);
    }

    public function getIsUseWhitelist($storeId = null)
    {
        return $this->getValue('is_email_whitelist_enabled', $storeId);
    }

    public function isBlockWebViewEnabled($storeId = null): bool
    {
        return (bool) $this->getValue('block_web_view', $storeId);
    }

    public function getWebhookId($storeId = null): string
    {
        return $this->getValue('webhook_id', $storeId) ?? '';
    }

    public function getEnableIframeCheckout($storeId = null): bool
    {
        return (bool) $this->getValue('enable_iframe_checkout', $storeId);
    }

    /**
     * @param null $storeId
     * @return string
     */
    public function getOrderStatusShouldBeCaptured($storeId = null): string
    {
        return $this->getValue('capture_payment/order_status_should_be_captured', $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function enabledDebug($storeId = null): bool {
        return (bool) $this->getValue('debug', $storeId);
    }

    /**
     * @param null $storeId
     * @return string
     */
    public function getOrderStatusShouldBeRefunded($storeId = null): string
    {
        return $this->getValue('console/order_status_should_be_refunded', $storeId);
    }
}
