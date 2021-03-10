<?php
namespace Tamara\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\Config as MagentoPaymentConfig;

class InstalmentConfig extends MagentoPaymentConfig
{
    const PAYMENT_TYPE_CODE = 'tamara_pay_by_instalments',
          MIN_LIMIT = 'min_limit',
          MAX_LIMIT = 'max_limit',
          TITLE = 'title',
          ACTIVE = 'active';
    const NUMBER_OF_INSTALMENTS = 3;

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
        $this->serializer = $serializer;
    }

    public function getMinLimit($storeId = null)
    {
        return $this->getValue(self::MIN_LIMIT, $storeId);
    }

    public function getMaxLimit($storeId = null)
    {
        return $this->getValue(self::MAX_LIMIT, $storeId);
    }

    public function getPayByInstalmentsTitle($storeId = null)
    {
        return $this->getValue(self::TITLE, $storeId);
    }

    public function isEnabled($storeId = null) {
        return (bool) $this->getValue(self::ACTIVE, $storeId);
    }
}
