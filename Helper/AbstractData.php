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
     * @return array|mixed
     * @throws \Tamara\Exception\RequestDispatcherException
     */
    public function getPaymentTypes($countryCode = 'SA') {
        $cachedPaymentTypes = $this->getPaymentTypesCached($countryCode);
        if (empty($cachedPaymentTypes)) {
            $adapter = $this->tamaraAdapterFactory->create();
            $cachedPaymentTypes = $adapter->getPaymentTypes($countryCode);
            $this->cachePaymentTypes($cachedPaymentTypes, $countryCode);
        }
        return $cachedPaymentTypes;
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

        $storeCountryCode = $this->getStoreCountryCode($storeId);
        $paymentTypes = $this->getPaymentTypes($storeCountryCode);
        $result = $paymentTypes;

        //validate currency
        $storeCurrencyCode = $this->getStoreCurrencyCode($storeId);
        foreach ($paymentTypes as $type) {
            if ($type['currency'] != $storeCurrencyCode) {
                unset($result[$type['name']]);
            }
        }
        return $result;
    }

    /**
     * @param array $paymentTypes
     * @param $countryCode
     */
    private function cachePaymentTypes(array $paymentTypes, $countryCode) {
        $paymentTypesAsStr = json_encode($paymentTypes);
        $this->magentoCache->save($paymentTypesAsStr, $this->getPaymentTypesCacheIdentifier($countryCode), [],
            self::PAYMENT_TYPES_CACHE_LIFE_TIME);
    }

    /**
     * @param $countryCode
     * @return array|mixed
     */
    private function getPaymentTypesCached($countryCode) {
        $cachedStr = $this->magentoCache->load($this->getPaymentTypesCacheIdentifier($countryCode));
        if (empty($cachedStr)) {
            return [];
        }
        return json_decode($cachedStr, true);
    }

    /**
     * @param $countryCode
     * @return string
     */
    protected function getPaymentTypesCacheIdentifier($countryCode) {
        return self::PAYMENT_TYPES_CACHE_IDENTIFIER . $countryCode;
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getStoreCountryCode($storeId = null) {
        return $this->scopeConfig->getValue("general/country/default", ScopeInterface::SCOPE_STORES, $storeId);
    }
}