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
        if ($this->getApiEnvironment($storeId) == \Tamara\Checkout\Api\Data\CheckoutInformationInterface::PRODUCTION_API_ENVIRONMENT) {
            return \Tamara\Checkout\Api\Data\CheckoutInformationInterface::PRODUCTION_API_URL;
        } else {
            return \Tamara\Checkout\Api\Data\CheckoutInformationInterface::SANDBOX_API_URL;
        }
    }

    public function getApiEnvironment($storeId = null) {
        return $this->getValue('api_environment', $storeId);
    }

    public function getMerchantSuccessUrl($storeId = null)
    {
        return $this->getValue('merchant_success_url', $storeId);
    }

    public function getMerchantFailureUrl($storeId = null)
    {
        return $this->getValue('merchant_failure_url', $storeId);
    }

    public function getSendEmailWhen($storeId = null) {
        $valueAsStr = $this->getValue('send_email_when', $storeId);
        return explode(",",$valueAsStr);
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

    public function getCheckoutOrderCreateStatus($storeId = null) {
        return $this->getValue('checkout_order_statuses/checkout_order_created_status', $storeId);
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
        return "https://www.tamara.co/about-us.html";
    }

    public function isProductionApiEnvironment($storeId = null) {
        return $this->getApiEnvironment($storeId) == \Tamara\Checkout\Api\Data\CheckoutInformationInterface::PRODUCTION_API_ENVIRONMENT;
    }

    public function getLinkLoginTamara($storeId = null)
    {
        if ($this->isProductionApiEnvironment()) {
            return "https://app.tamara.co";
        } else {
            return "https://app-sandbox.tamara.co";
        }
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
        return false;
    }

    public function getAutoGenerateInvoice($storeId = null) {
        return (int) $this->getValue('auto_generate_invoice', $storeId);
    }

    public function isPhoneVerified($storeId = null) {
        return (bool) $this->getValue('phone_verified', $storeId);
    }

    public function useMagentoCheckoutSuccessPage($storeId = null) {
        return (bool) $this->getValue('use_magento_success_page', $storeId);
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

    /**
     * @param null $storeId
     * @return bool
     */
    public function getEnableTamaraPdpWidget($storeId = null) {
        return (bool) $this->getValue('enable_pdp_widget', $storeId);
    }

    public function getExcludeProductIds($storeId = null) {
        return $this->getValue('exclude_product_ids', $storeId);
    }
}
