<?php

namespace Tamara\Checkout\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Model\Order\MerchantUrl;

class MerchantUrlDataBuilder implements BuilderInterface
{
    public const
        MERCHANT_URL = 'merchant_url',
        TAMARA_PAYMENT = 'tamara/payment';

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var BaseConfig
     */
    protected $baseConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * MerchantUrlDataBuilder constructor.
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        BaseConfig $baseConfig
    )
    {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->baseConfig = $baseConfig;
    }

    public function build(array $buildSubject)
    {
        $orderId = $buildSubject['order_result_id'];
        $merchantUrl = new MerchantUrl();

        try {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            $storeId = $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException $e) {
            return [self::MERCHANT_URL => $merchantUrl];
        }

        $urlPattern = '%s%s/%d/%s';
        $successUrl = $this->baseConfig->getMerchantSuccessUrl($storeId);
        if (empty($successUrl)) {
            $successUrl = sprintf($urlPattern, $baseUrl, self::TAMARA_PAYMENT, $orderId, 'success');
        }
        $cancelUrl = $this->baseConfig->getMerchantCancelUrl($storeId);
        if (empty($cancelUrl)) {
            $cancelUrl = sprintf($urlPattern, $baseUrl, self::TAMARA_PAYMENT, $orderId, 'cancel');
        }
        $failureUrl = $this->baseConfig->getMerchantFailureUrl($storeId);
        if (empty($failureUrl)) {
            $failureUrl = sprintf($urlPattern, $baseUrl, self::TAMARA_PAYMENT, $orderId, 'failure');
        }
        $notificationUrl = sprintf('%s%s/%s%s', $baseUrl, self::TAMARA_PAYMENT, 'notification', '?storeId=' . $storeId);
        $merchantUrl->setNotificationUrl($notificationUrl);
        $merchantUrl->setSuccessUrl($successUrl);
        $merchantUrl->setCancelUrl($cancelUrl);
        $merchantUrl->setFailureUrl($failureUrl);

        return [self::MERCHANT_URL => $merchantUrl];
    }
}
