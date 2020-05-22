<?php

namespace Tamara\Checkout\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Model\Order\MerchantUrl;

class MerchantUrlDataBuilder implements BuilderInterface
{
    public const
        MERCHANT_URL = 'merchant_url',
        TAMARA_PAYMENT = 'tamara/payment';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * MerchantUrlDataBuilder constructor.
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
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
        $notificationUrl = sprintf('%s%s/%s%s', $baseUrl, self::TAMARA_PAYMENT, 'notification', '?storeId=' . $storeId);

        $merchantUrl->setSuccessUrl(sprintf($urlPattern, $baseUrl, self::TAMARA_PAYMENT, $orderId, 'success'));
        $merchantUrl->setFailureUrl(sprintf($urlPattern, $baseUrl, self::TAMARA_PAYMENT, $orderId, 'failure'));
        $merchantUrl->setCancelUrl(sprintf($urlPattern, $baseUrl, self::TAMARA_PAYMENT, $orderId, 'cancel'));
        $merchantUrl->setNotificationUrl($notificationUrl);

        return [self::MERCHANT_URL => $merchantUrl];
    }
}
