<?php

declare(strict_types=1);

namespace Tamara\Checkout\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Config\Config as MagentoPaymentConfig;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Api\ConfigRepositoryInterface;
use Tamara\Checkout\Api\Data\ConfigInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig as GatewayConfig;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;
use Tamara\Checkout\Gateway\Config\InstalmentConfig;
use Tamara\Checkout\Gateway\Request\MerchantUrlDataBuilder;

class ConfigRepository extends GatewayConfig implements ConfigRepositoryInterface
{
    /**
     * @var \Tamara\Checkout\Helper\AbstractData
     */
    protected $tamaraHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PayLaterConfig
     */
    private $payLaterConfig;

    /**
     * @var InstalmentConfig
     */
    private $instalmentConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $serializer,
        StoreManagerInterface $storeManager,
        PayLaterConfig $payLaterConfig,
        InstalmentConfig $instalmentConfig,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        $methodCode = self::CODE,
        $pathPattern = MagentoPaymentConfig::DEFAULT_PATH_PATTERN
    ) {
        GatewayConfig::__construct($scopeConfig, $serializer, $methodCode, $pathPattern);
        $this->storeManager = $storeManager;
        $this->payLaterConfig = $payLaterConfig;
        $this->instalmentConfig = $instalmentConfig;
        $this->tamaraHelper = $tamaraHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): ConfigInterface
    {
        return new Config(
            $this->getApiUrl(),
            $this->getMerchantToken(),
            $this->getNotificationUrl(),
            $this->getPaymentLimit()
        );
    }

    private function getNotificationUrl(): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);

        return sprintf('%s%s/%s', $baseUrl, MerchantUrlDataBuilder::TAMARA_PAYMENT, 'notification');
    }

    private function getPaymentLimit(): string
    {
        $data = [
            [
                'name' => $this->payLaterConfig->getPayLaterTitle(),
                'min_limit' => (float) $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]['min_limit'],
                'max_limit' => (float) $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]['max_limit']
            ],
            [
                'name' => $this->instalmentConfig->getPayByInstalmentsTitle(),
                'min_limit' => (float) $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]['min_limit'],
                'max_limit' => (float) $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]['max_limit']
            ],
        ];

        return json_encode($data);
    }
}
