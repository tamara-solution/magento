<?php
namespace Tamara\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\Config as MagentoPaymentConfig;

class PayNextMonthConfig extends MagentoPaymentConfig
{
    const PAY_NEXT_MONTH = 'PAY_NEXT_MONTH';
    const PAYMENT_TYPE_CODE = 'tamara_pay_next_month',
        TITLE = 'title',
        ACTIVE = 'active';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     * @param null|string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $serializer,
        $methodCode = self::PAYMENT_TYPE_CODE,
        $pathPattern = MagentoPaymentConfig::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }
}
