<?php

namespace Tamara\Checkout\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\PaymentHelper;

class AbstractData extends \Tamara\Checkout\Helper\Core
{
    const PAYMENT_TYPES_CACHE_IDENTIFIER = 'payment_types_cache';
    const PAYMENT_TYPES_CACHE_LIFE_TIME = 1800; //30 minutes

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $locale;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $magentoCache;

    /**
     * @var BaseConfig
     */
    protected $tamaraConfig;

    /**
     * @var \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory
     */
    protected $tamaraAdapterFactory;

    /**
     * @var \Magento\Payment\Model\Method\Logger
     */
    private $tamaraPaymentLogger;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(
        Context $context,
        \Magento\Framework\Locale\Resolver $locale,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\CacheInterface $magentoCache,
        BaseConfig $tamaraConfig,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory
    ) {
        $this->locale = $locale;
        $this->magentoCache = $magentoCache;
        $this->tamaraConfig = $tamaraConfig;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        parent::__construct($context, \Magento\Framework\App\ObjectManager::getInstance(), $storeManager);
    }

    /**
     * @return bool
     */
    public function isArabicLanguage()
    {
        return $this->startsWith($this->getLocale(), 'ar_');
    }

    /**
     * @return string|null
     */
    public function getLocale()
    {
        return $this->locale->getLocale();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreCurrencyCode($storeId = null)
    {
        return $this->storeManager->getStore($storeId)->getCurrentCurrencyCode();
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentStore()
    {
        return $this->storeManager->getStore();
    }

    public function log(array $data)
    {
        if (count($data) && is_string($data[0])) {
            $data[0] = "Tamara - " . $data[0];
            if ($this->getOutput()) {
                $this->output->writeln($data[0]);
            }
        }
        $this->getLogger()->debug($data, null, $this->tamaraConfig->enabledDebug());
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @return \Magento\Payment\Model\Method\Logger
     */
    public function getLogger()
    {
        if (!$this->tamaraPaymentLogger) {
            try {
                $this->tamaraPaymentLogger = $this->getObject('TamaraCheckoutLogger');
            } catch (\Exception $exception) {
                $this->tamaraPaymentLogger = $this->createObject('TamaraCheckoutLogger');
            }
        }
        return $this->tamaraPaymentLogger;
    }

    public function isTamaraPayment($method)
    {
        return PaymentHelper::isTamaraPayment($method);
    }

    /**
     * @param string $countryCode
     * @param string $currencyCode
     * @param int $storeId
     * @return array|mixed
     * @throws \Tamara\Exception\RequestDispatcherException
     */
    public function getPaymentTypes($countryCode = 'SA', $currencyCode = '',  $storeId = 0) {
        $cachedPaymentTypes = $this->getPaymentTypesCached($countryCode, $currencyCode, $storeId);
        if ($cachedPaymentTypes === false) {
            $adapter = $this->tamaraAdapterFactory->create($storeId);
            $cachedPaymentTypes = $adapter->getPaymentTypes($countryCode, $currencyCode);
            $this->cachePaymentTypes($cachedPaymentTypes, $countryCode, $currencyCode, $storeId);
        }
        return $cachedPaymentTypes;
    }

    /**
     * @param $currency
     * @param $storeId
     * @return bool
     */
    public function isAllowedCurrency($currency, $storeId) {
        if (!in_array($currency, explode(',', \Tamara\Checkout\Model\Method\Checkout::ALLOWED_CURRENCIES))) {
            return false;
        }
        return $this->isAllowedCountry(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$currency], $storeId);
    }

    /**
     * @param $country
     * @param $storeId
     * @return bool
     */
    public function isAllowedCountry($country, $storeId) {
        if (!in_array($country, explode(',', \Tamara\Checkout\Model\Method\Checkout::ALLOWED_COUNTRIES))) {
            return false;
        }
        if ((int)$this->getTamaraConfig()->getValue('allowspecific', $storeId) === 1) {
            $availableCountries = explode(
                ',',
                $this->getTamaraConfig()->getValue('specificcountry', $storeId)
            );

            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $storeId
     * @return array|mixed
     * @throws \Tamara\Exception\RequestDispatcherException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentTypesOfStore($storeId = null) {
        if (is_null($storeId)) {
            $storeId = $this->getCurrentStore()->getId();
        }

        $storeCurrencyCode = $this->getStoreCurrencyCode($storeId);
        if (!$this->isAllowedCurrency($storeCurrencyCode, $storeId)) {
            return [];
        }
        $paymentTypes = $this->getPaymentTypes(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrencyCode], $storeCurrencyCode, $storeId);
        foreach ($paymentTypes as $methodCode => $paymentType) {
            if (!$this->isPaymentMethodEnabled($methodCode, $storeId)) {
                unset($paymentTypes[$methodCode]);
            }
        }
        return $paymentTypes;
    }

    /**
     * @param array $paymentTypes
     * @param $countryCode
     * @param $currencyCode
     * @param int $storeId
     */
    private function cachePaymentTypes(array $paymentTypes, $countryCode, $currencyCode, $storeId) {
        $paymentTypesAsStr = json_encode($paymentTypes);
        $this->magentoCache->save($paymentTypesAsStr, $this->getPaymentTypesCacheIdentifier($countryCode, $currencyCode, $storeId), [],
            self::PAYMENT_TYPES_CACHE_LIFE_TIME);
    }

    /**
     * @param $countryCode
     * @param $currencyCode
     * @param $storeId
     * @return array|mixed
     */
    private function getPaymentTypesCached($countryCode, $currencyCode, $storeId) {
        $cachedStr = $this->magentoCache->load($this->getPaymentTypesCacheIdentifier($countryCode, $currencyCode, $storeId));
        if ($cachedStr === false) {
            return $cachedStr;
        }
        if (empty($cachedStr)) {
            return [];
        }
        return json_decode($cachedStr, true);
    }

    /**
     * @param $countryCode
     * @param $currencyCode
     * @param $storeId
     * @return string
     */
    protected function getPaymentTypesCacheIdentifier($countryCode, $currencyCode, $storeId) {
        return self::PAYMENT_TYPES_CACHE_IDENTIFIER . $countryCode. $currencyCode . $storeId;
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getStoreCountryCode($storeId = null) {
        return $this->scopeConfig->getValue("general/country/default", ScopeInterface::SCOPE_STORES, $storeId);
    }

    /**
     * @param $paymentMethodCode
     * @param $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isPaymentMethodEnabled($paymentMethodCode, $storeId = null) {
        if ($storeId === null) {
            $storeId = $this->getCurrentStore()->getId();
        }
        return boolval($this->scopeConfig->getValue(
            sprintf(\Magento\Payment\Gateway\Config\Config::DEFAULT_PATH_PATTERN, $paymentMethodCode, "active"),
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getTamaraConfig() {
        return $this->tamaraConfig;
    }
}