<?php

namespace Tamara\Checkout\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;

class ConfigProvider implements ConfigProviderInterface
{
    private const TAMARA_IFRAME_CHECKOUT = 'tamara_iframe_checkout';
    /**
     * @var PayLaterConfig
     */
    private $config;

    /**
     * @var BaseConfig
     */
    private $baseConfig;

    public function __construct(
        PayLaterConfig $config,
        BaseConfig $baseConfig
    ) {
        $this->config = $config;
        $this->baseConfig = $baseConfig;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {

        return [
            'payment' => [
                PayLaterConfig::PAYMENT_TYPE_CODE => $this->getMinMaxOrder(),
                self::TAMARA_IFRAME_CHECKOUT => $this->baseConfig->getEnableIframeCheckout()
            ]
        ];
    }

    private function getMinMaxOrder()
    {
        return [
            'min_limit' => $this->config->getMinLimit(),
            'max_limit' => $this->config->getMaxLimit(),
        ];
    }
}