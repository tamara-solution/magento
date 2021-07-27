<?php


namespace Tamara\Checkout\Model\Config\Backend;

use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Helper\AbstractData;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class EnablePaymentType extends \Magento\Framework\App\Config\Value
{
    protected $tamaraConfig;
    protected $tamaraAdapterFactory;
    protected $tamaraHelper;
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        TamaraAdapterFactory $tamaraAdapterFactory,
        BaseConfig $tamaraConfig,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->tamaraConfig = $tamaraConfig;
        $this->tamaraHelper = $tamaraHelper;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return \Magento\Framework\App\Config\Value
     * @throws \Exception
     */
    public function beforeSave()
    {
        if (!$this->isValueChanged()) {
            return parent::beforeSave();
        }
        if (!empty($this->getValue())) {
            $adapter = $this->tamaraAdapterFactory->create();
            $storeCountryCode = $this->tamaraHelper->getStoreCountryCode();
            $response = $adapter->getClient()->getPaymentTypes($storeCountryCode);
            if (!$response->isSuccess()) {
                throw new \Exception("Tamara checkout config, error when get payment types, error message: " . __($response->getMessage()));
            }
            $paymentTypes = $adapter->parsePaymentTypesResponse($response);
            if ($this->getPath() == "payment/tamara_pay_later/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\PayLaterConfig::PAYMENT_TYPE_CODE])) {
                    throw new \Exception("Pay later is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE])) {
                    throw new \Exception("Pay in 3 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_4/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_4])) {
                    throw new \Exception("Pay in 4 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_5/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_5])) {
                    throw new \Exception("Pay in 5 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_6/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_6])) {
                    throw new \Exception("Pay in 6 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_7/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_7])) {
                    throw new \Exception("Pay in 7 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_8/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_8])) {
                    throw new \Exception("Pay in 8 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_9/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_9])) {
                    throw new \Exception("Pay in 9 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_10/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_10])) {
                    throw new \Exception("Pay in 10 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_11/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_11])) {
                    throw new \Exception("Pay in 11 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments_12/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE_12])) {
                    throw new \Exception("Pay in 12 installments is not allowed for this merchant, please contact Tamara support");
                }
            }
        }
        return parent::beforeSave();
    }
}