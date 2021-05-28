<?php
namespace Tamara\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\Config as MagentoPaymentConfig;

class PayLaterConfig extends MagentoPaymentConfig
{
    const PAYMENT_TYPE_CODE = 'tamara_pay_later',
          MIN_LIMIT = 'min_limit',
          MAX_LIMIT = 'max_limit',
          TITLE = 'title',
          ACTIVE = 'active';

    /**
     * @var \Tamara\Checkout\Helper\AbstractData
     */
    protected $tamaraHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     * @param null|string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $serializer,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        $methodCode = self::PAYMENT_TYPE_CODE,
        $pathPattern = MagentoPaymentConfig::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->serializer = $serializer;
        $this->tamaraHelper = $tamaraHelper;
    }

    public function getPayLaterTitle($storeId = null)
    {
        return $this->getValue(self::TITLE, $storeId);
    }

    public function isEnabled($storeId = null) {
        $paymentTypes = $this->tamaraHelper->getPaymentTypesOfStore($storeId);
        return (bool) $this->getValue(self::ACTIVE, $storeId)
            && isset($paymentTypes[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]);
    }
}
