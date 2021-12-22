<?php

namespace Tamara\Checkout\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;
use Tamara\Checkout\Gateway\Config\InstalmentConfig;

class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Tamara\Checkout\Helper\AbstractData
     */
    protected $tamaraHelper;

    /**
     * @var $locale \Magento\Framework\Locale\Resolver
     */
    private $locale;

    /**
     * @var PayLaterConfig
     */
    private $payLaterConfig;

    /**
     * @var InstalmentConfig
     */
    private $instalmentConfig;

    /**
     * @var BaseConfig
     */
    private $baseConfig;

    public function __construct(
        \Magento\Framework\Locale\Resolver $locale,
        PayLaterConfig $payLaterConfig,
        InstalmentConfig $instalmentConfig,
        BaseConfig $baseConfig,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper
    ) {
        $this->locale = $locale;
        $this->payLaterConfig = $payLaterConfig;
        $this->instalmentConfig = $instalmentConfig;
        $this->baseConfig = $baseConfig;
        $this->tamaraHelper = $tamaraHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Tamara\Exception\RequestDispatcherException
     */
    public function getConfig()
    {
        $config = [
            'tamara' => [
                'use_magento_checkout_success' => $this->baseConfig->useMagentoCheckoutSuccessPage(),
                'locale_code' => $this->getLocale()
            ]
        ];
        $storeId = $this->tamaraHelper->getCurrentStore()->getId();
        $storeCurrency = $this->tamaraHelper->getStoreCurrencyCode($storeId);
        if (!$this->tamaraHelper->isAllowedCurrency($storeCurrency, $storeId)) {
            return [
                'payment' => $config
            ];
        }
        $paymentTypes = $this->tamaraHelper->getPaymentTypes(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrency],
            $storeCurrency, $storeId);
        foreach ($paymentTypes as $methodCode => $type) {
            $config[$methodCode] = $type;
        }
        return [
            'payment' => $config
        ];
    }

    /**
     * @return string|null
     */
    public function getLocale() {
        return $this->locale->getLocale();
    }
}