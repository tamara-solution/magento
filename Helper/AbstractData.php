<?php

namespace Tamara\Checkout\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\PaymentHelper;
use Tamara\Exception\RequestException;
use Tamara\Response\Checkout\CheckPaymentOptionsAvailabilityResponse;

class AbstractData extends \Tamara\Checkout\Helper\Core
{
    const PAYMENT_TYPES_CACHE_IDENTIFIER = 'payment_types_cache';
    const PAYMENT_TYPES_CACHE_LIFE_TIME = 1800; //30 minutes
    const SINGLE_CHECKOUT_CACHE_LIFE_TIME = 86400; //1 day
    const ORDER_PAYMENT_TYPES_CACHE_LIFE_TIME = 300; //5 minutes

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
     */
    public function getPaymentTypes($countryCode = 'SA', $currencyCode = '',  $storeId = 0) {
        $cachedPaymentTypes = $this->getPaymentTypesCached($countryCode, $currencyCode, $storeId);
        if ($cachedPaymentTypes === false) {
            $adapter = $this->tamaraAdapterFactory->create($storeId);
            $cachedPaymentTypes = $adapter->getPaymentTypes($countryCode, $currencyCode);

            //if there is an error when api call
            if ($cachedPaymentTypes === null) {
                $cachedPaymentTypes = [];
                $this->cachePaymentTypes($cachedPaymentTypes, $countryCode, $currencyCode, $storeId, \Tamara\Checkout\Model\Adapter\TamaraAdapter::DISABLE_TAMARA_CACHE_LIFE_TIME);
            } else {
                $this->cachePaymentTypes($cachedPaymentTypes, $countryCode, $currencyCode, $storeId);
            }
        }
        return $cachedPaymentTypes;
    }

    /**
     * @param \Tamara\Model\Money $totalAmount
     * @param string $countryCode
     * @param null $items
     * @param null $consumer
     * @param null $shippingAddress
     * @param null $riskAssessment
     * @param array $additionalData
     * @param int $storeId
     * @return array
     */
    public function getPaymentTypesV2(\Tamara\Model\Money $totalAmount, string $countryCode, $items = null,
        $consumer = null, $shippingAddress = null, $riskAssessment = null, $additionalData = [], $storeId = 0) {
        $adapter = $this->tamaraAdapterFactory->create($storeId);
        if ($adapter->getDisableTamara()) {
            return [];
        }
        try {
            $request = new \Tamara\Request\Checkout\GetPaymentTypesV2Request(
                $totalAmount, $countryCode, $items, $consumer, $shippingAddress, $riskAssessment, $additionalData
            );
            $response = $adapter->getClient()->getPaymentTypesV2($request);
            return $adapter->parsePaymentTypesResponse($response);
        } catch (RequestException $requestException) {
            $adapter->setDisableTamara(true);
            $this->getLogger()->debug(["Tamara" => $requestException->getMessage()]);
        } catch (\Exception $exception) {
            $this->getLogger()->debug(["Tamara" => $exception->getMessage()]);
        }
        return [];
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return \Magento\Quote\Api\Data\AddressInterface|\Magento\Quote\Model\Quote\Address
     */
    public function getShippingAddressFromQuote(\Magento\Quote\Api\Data\CartInterface $quote) {
        $shippingAddress = $quote->getShippingAddress();
        $useBillingAddress = false;
        if ($shippingAddress && $shippingAddress->getId()) {
            $shippingMethod = strval($shippingAddress->getShippingMethod());

            /**
             * @var \Tamara\Checkout\Model\AddressRepository $tamaraAddressRepositoryObj
             */
            $tamaraAddressRepositoryObj = $this->createObject(\Tamara\Checkout\Model\AddressRepository::class);
            foreach ($tamaraAddressRepositoryObj->getClickAndCollectMethods() as $method) {
                if ($this->startsWith($shippingMethod, $method)) {
                    $useBillingAddress = true;
                    break;
                }
            }
        } else {
            $useBillingAddress = true;
        }
        if ($useBillingAddress) {
            $shippingAddress = $quote->getBillingAddress();
        }
        return $shippingAddress;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     */
    public function getPaymentTypesForQuote($quote) {
        $storeId = $quote->getStoreId();
        $storeCurrency = $this->getStoreCurrencyCode($quote->getStoreId());
        $shippingAddress = $this->getShippingAddressFromQuote($quote);
        $countryCode = "";
        $phoneNumber = "";
        if (isset(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrency])) {
            $countryCode = \Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrency];
        }
        if ($shippingAddress !== null) {
            if (!empty($shippingAddress->getCountryId())) {
                $countryCode = $shippingAddress->getCountryId();
            }
            if (!empty($shippingAddress->getTelephone())) {
                $phoneNumber = strval($shippingAddress->getTelephone());
            }
        }
        if (empty($countryCode)) {
            return [];
        }
        return $this->getPaymentTypesByOrderInfo($countryCode,
            $storeCurrency, floatval($quote->getGrandTotal()), $phoneNumber , true, $storeId
        );
    }

    public function getPaymentTypesByOrderInfo($countryCode, $currencyCode, $orderValue, $phoneNumber, $isVip = true, $storeId = 0) {
        $cacheKey = $countryCode . $currencyCode . strval($orderValue) . $phoneNumber . strval(intval($isVip)) . strval($storeId);
        if (($val = $this->magentoCache->load($cacheKey)) !== false) {
            if (empty($val)) {
                return [];
            }
            return json_decode($val, true);
        }
        $paymentTypes = $this->checkPaymentOptionsAvailability($countryCode, $currencyCode, $orderValue, $phoneNumber, $isVip, $storeId)['payment_types'];
        $this->magentoCache->save(json_encode($paymentTypes), $cacheKey, [], self::ORDER_PAYMENT_TYPES_CACHE_LIFE_TIME);
        return $paymentTypes;
    }

    public function checkPaymentOptionsAvailability($countryCode, $currencyCode, $orderValue, $phoneNumber, $isVip = true, $storeId = 0) {
        $result = [
            'has_available_payment_options' => false,
            'single_checkout_enabled' => false,
            'payment_types' => []
        ];
        if (!isset(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$currencyCode])
        || \Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$currencyCode] != $countryCode
        ) {
            return $result;
        }
        $adapter = $this->tamaraAdapterFactory->create($storeId);
        if ($adapter->getDisableTamara()) {
            return $result;
        }
        try {
            $paymentOptionsAvailability = new \Tamara\Model\Checkout\PaymentOptionsAvailability(
                $countryCode,
                new \Tamara\Model\Money($orderValue, $currencyCode),
                $phoneNumber,
                $isVip
            );
            $request = new \Tamara\Request\Checkout\CheckPaymentOptionsAvailabilityRequest($paymentOptionsAvailability);
            $response = $adapter->getClient()->checkPaymentOptionsAvailability($request);
            $result = $this->parsePaymentOptionsAvailabilityResponse($response, $currencyCode, $storeId);
        } catch (RequestException $requestException) {
            $adapter->setDisableTamara(true);
        } catch (\Exception $exception) {
            $this->getLogger()->debug(["Tamara" => $exception->getMessage()]);
        }
        if ($this->isSingleCheckoutEnabled($storeId) != $result['single_checkout_enabled']) {
            $this->setSingleCheckoutEnabled($result['single_checkout_enabled'], \Magento\Store\Model\ScopeInterface::SCOPE_STORES , $storeId);
        }
        return $result;
    }

    /**
     * @param CheckPaymentOptionsAvailabilityResponse $response
     * @param $currencyCode
     * @param $storeId
     * @return array
     */
    public function parsePaymentOptionsAvailabilityResponse($response, $currencyCode, $storeId) {
        $result = [
            'has_available_payment_options' => false,
            'single_checkout_enabled' => false,
            'payment_types' => []
        ];
        if ($response->isSuccess()) {
            $result['has_available_payment_options'] = $response->hasAvailablePaymentOptions();
            $result['single_checkout_enabled'] = $response->isSingleCheckoutEnabled();
            $paymentTypes = [];
            $allPaymentTypes = $this->getPaymentTypes(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$currencyCode],
                $currencyCode, $storeId);
            foreach ($response->getAvailablePaymentLabels() as $paymentType) {
                $typeName = "";
                if ($paymentType['payment_type'] == \Tamara\Checkout\Gateway\Config\PayLaterConfig::PAY_BY_LATER) {
                    $typeName = \Tamara\Checkout\Gateway\Config\PayLaterConfig::PAYMENT_TYPE_CODE;
                }
                if ($paymentType['payment_type'] == \Tamara\Checkout\Gateway\Config\PayNextMonthConfig::PAY_NEXT_MONTH) {
                    $typeName = \Tamara\Checkout\Gateway\Config\PayNextMonthConfig::PAYMENT_TYPE_CODE;
                }
                if ($paymentType['payment_type'] == \Tamara\Checkout\Gateway\Config\PayNowConfig::PAY_NOW) {
                    $typeName = \Tamara\Checkout\Gateway\Config\PayNowConfig::PAYMENT_TYPE_CODE;
                }
                if ($paymentType['payment_type'] == \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAY_BY_INSTALMENTS) {
                    $typeName = \Tamara\Checkout\Gateway\Config\InstalmentConfig::getInstallmentPaymentCode($paymentType['instalment']);
                }
                $title = $paymentType['description_ar'];
                if (!$this->isArabicLanguage()) {
                    $title = $paymentType['description_en'];
                }
                if (!empty($typeName)) {
                    $paymentTypes[$typeName] = [
                        'name' => $typeName,
                        'currency' => $currencyCode,
                        'description' => $paymentType['description_en'],
                        'description_ar' => $paymentType['description_ar'],
                        'min_limit' => 1,
                        'max_limit' => 999999999,
                        'title' => $title,
                        'is_installment' => ($paymentType['payment_type'] == \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAY_BY_INSTALMENTS),
                        'is_none_validated_method' => false
                    ];
                    if (empty($paymentType['instalment'])) {
                        $paymentTypes[$typeName]['number_of_instalments'] = 3;
                    } else {
                        $paymentTypes[$typeName]['number_of_instalments'] = $paymentType['instalment'];
                    }
                    if (isset($allPaymentTypes[$typeName])) {
                        $paymentTypes[$typeName]['min_limit'] = $allPaymentTypes[$typeName]['min_limit'];
                        $paymentTypes[$typeName]['max_limit'] = $allPaymentTypes[$typeName]['max_limit'];
                    }
                }
            }
            $result['payment_types'] = $paymentTypes;
        }

        return $result;
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
                strval($this->getTamaraConfig()->getValue('specificcountry', $storeId))
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
     * @param int $lifeTime
     */
    private function cachePaymentTypes(array $paymentTypes, $countryCode, $currencyCode, $storeId, $lifeTime = self::PAYMENT_TYPES_CACHE_LIFE_TIME) {
        $paymentTypesAsStr = json_encode($paymentTypes);
        $this->magentoCache->save($paymentTypesAsStr, $this->getPaymentTypesCacheIdentifier($countryCode, $currencyCode, $storeId), [],
            $lifeTime);
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
        return $this->tamaraConfig->isEnableTamaraPayment($storeId);
    }

    public function getTamaraConfig() {
        return $this->tamaraConfig;
    }

    public function isSingleCheckoutEnabled($storeId = null) {
        if ($storeId === null) {
            $scope = $this->getCurrentScope();
            $storeId = $this->getCurrentScopeId();
        } else {
            $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
        }
        return boolval($this->getSingleCheckoutCached($scope, $storeId));
    }

    private function getSingleCheckoutCached($scope, $scopeId) {
        return $this->magentoCache->load($this->getSingleCheckoutCacheIdentifier($scope, $scopeId));
    }

    private function getSingleCheckoutCacheIdentifier($scope, $scopeId) {
        return 'single_checkout_enabled' . $scope . $scopeId;
    }

    public function setSingleCheckoutEnabled($singleCheckoutValueCached, $scope, $storeId) {
        //set cache
        $this->magentoCache->save(intval($singleCheckoutValueCached),
            $this->getSingleCheckoutCacheIdentifier($scope, $storeId), [], self::SINGLE_CHECKOUT_CACHE_LIFE_TIME);
    }


    /**
     * @return string
     */
    public function getWidgetVersion() {
        if (empty($this->getMerchantPublicKey())) {
            return 'v1';
        }
        if ($this->isSingleCheckoutEnabled()) {
            return 'v2';
        }
        $paymentTypesOfStore = $this->getPaymentTypesOfStore();
        if (is_array($paymentTypesOfStore) && count($paymentTypesOfStore) < 2) {
            return 'v2';
        }
        return 'mixed';
    }

    public function getMerchantPublicKey($storeId = null) {
        if ($storeId === null) {
            $storeId = $this->getCurrentStore()->getId();
        }
        return $this->getTamaraConfig()->getPublicKey($storeId);
    }
}