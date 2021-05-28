<?php

namespace Tamara\Checkout\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;
use Tamara\Checkout\Gateway\Config\InstalmentConfig;

class ConfigProvider implements ConfigProviderInterface
{
    private const TAMARA_IFRAME_CHECKOUT = 'tamara_iframe_checkout';

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

    /**
     * @var array
     */
    private $paymentTypes;

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
            self::TAMARA_IFRAME_CHECKOUT => $this->baseConfig->getEnableIframeCheckout(),
            'tamara' => [
                'use_magento_checkout_success' => $this->baseConfig->useMagentoCheckoutSuccessPage()
            ]
        ];
        if (isset($this->getPaymentTypes()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER])) {
            $config[PayLaterConfig::PAYMENT_TYPE_CODE] = $this->getMinMaxOrderPayLater();
        }
        if (isset($this->getPaymentTypes()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS])) {
            $config[InstalmentConfig::PAYMENT_TYPE_CODE] = $this->getMinMaxOrderPayByInstalments();
        }
        return [
            'payment' => $config
        ];
    }

    private function getMinMaxOrderPayLater()
    {
        return [
            'min_limit' => $this->getPaymentTypes()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]['min_limit'],
            'max_limit' => $this->getPaymentTypes()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]['max_limit'],
        ];
    }

    private function getMinMaxOrderPayByInstalments() {
        return [
            'min_limit' => $this->getPaymentTypes()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]['min_limit'],
            'max_limit' => $this->getPaymentTypes()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]['max_limit'],
            'number_of_instalments' => InstalmentConfig::NUMBER_OF_INSTALMENTS,
            'locale_code' => $this->getLocale()
        ];
    }

    /**
     * @return string|null
     */
    public function getLocale() {
        return $this->locale->getLocale();
    }

    /**
     * @return array|mixed
     * @throws \Tamara\Exception\RequestDispatcherException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentTypes() {
        if ($this->paymentTypes === null) {
            $this->paymentTypes = $this->tamaraHelper->getPaymentTypesOfStore();
        }
        return $this->paymentTypes;
    }
}