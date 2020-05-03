<?php

namespace Tamara\Checkout\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Tamara\Checkout\Gateway\Config\PayLaterConfig;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var PayLaterConfig
     */
    private $config;

    /**
     * Constructor
     *
     * @param PayLaterConfig $config
     */
    public function __construct(
        PayLaterConfig $config
    ) {
        $this->config = $config;
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
                PayLaterConfig::PAYMENT_TYPE_CODE => $this->getMinMaxOrder()
            ]
        ];
    }

    private function getMinMaxOrder()
    {
        return [
            'min_limit' => $this->config->getMinLimit(),
            'max_limit' => $this->config->getMaxLimit()
        ];
    }
}