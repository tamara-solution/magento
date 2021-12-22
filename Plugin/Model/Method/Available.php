<?php

namespace Tamara\Checkout\Plugin\Model\Method;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\Method\Logger;
use Tamara\Checkout\Api\EmailWhiteListRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\PaymentHelper;

class Available
{
    private $logger;

    private $config;

    private $emailWhiteListRepository;

    private $httpHeader;

    private $tamaraHelper;

    public function __construct(
        Logger $logger,
        BaseConfig $config,
        EmailWhiteListRepositoryInterface $emailWhiteListRepository,
        Header $httpHeader,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->emailWhiteListRepository = $emailWhiteListRepository;
        $this->httpHeader = $httpHeader;
        $this->tamaraHelper = $tamaraHelper;
    }


    public function afterGetAvailableMethods(
        \Magento\Payment\Model\MethodList $subject,
        $availableMethods,
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        if ($this->tamaraHelper->isAdminArea()) {
            return $this->removeTamaraMethod($availableMethods);
        }

        if (!$this->tamaraHelper->isAllowedCurrency($quote->getCurrency()->getQuoteCurrencyCode(), $quote->getStoreId())) {
            return $this->removeTamaraMethod($availableMethods);
        }

        //Remove Tamara payment for these products
        $excludeProductIds = explode(",", $this->config->getExcludeProductIds($quote->getStoreId()));
        $quoteItems = $quote->getItems();
        foreach ($quoteItems as $item) {
            
            /**
             * @var \Magento\Quote\Model\Quote\Item $item
             */
            if (in_array($item->getProductId(), $excludeProductIds)) {
                return $this->removeTamaraMethod($availableMethods);
            }
        }

        //If block webview
        $userAgent = $this->httpHeader->getHttpUserAgent();
        if ($this->config->isBlockWebViewEnabled($quote->getStoreId())) {
            if (!$this->isWebView($userAgent) || $this->isRestful()) {
                return $this->removeTamaraMethod($availableMethods);
            }
        }

        //If enable whitelist
        if ($this->config->getIsUseWhitelist($quote->getStoreId())) {
            if ($quote->getCustomerIsGuest()) {
                return $this->removeTamaraMethod($availableMethods);
            }

            $email = $quote->getCustomer()->getEmail();
            if (!$email || !$this->emailWhiteListRepository->isEmailWhitelisted($email)) {
                return $this->removeTamaraMethod($availableMethods);
            }
        }
        $availableMethods = $this->filterUnAvailableMethods($availableMethods, $quote->getStoreId());

        //If disable warning message under / over limit
        if (!$this->config->isDisplayWarningMessageIfOrderOverUnderLimit($quote->getStoreId())) {
            $quoteTotal = $quote->getGrandTotal();
            return $this->filterUnderOverLimit($availableMethods, $quoteTotal, $quote->getStoreId());
        }
        return $availableMethods;
    }

    private function filterUnAvailableMethods($availableMethods, $storeId)
    {
        $storeCurrency = $this->tamaraHelper->getStoreCurrencyCode($storeId);
        $paymentTypes = $this->tamaraHelper->getPaymentTypes(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrency],
            $storeCurrency, $storeId);
        foreach ($availableMethods as $key => $method) {
            $methodCode = $method->getCode();
            if (PaymentHelper::isTamaraPayment($methodCode) && !isset($paymentTypes[$methodCode])) {
                unset($availableMethods[$key]);
            }
        }
        return $availableMethods;
    }

    private function isWebView(string $userAgent): bool
    {
        $this->logger->debug(['User Agent' => $userAgent]);

        if ((strpos($userAgent, 'Mobile/') !== false) && (strpos($userAgent, 'Safari/') === false)) {
            return true;
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return true;
        }

        return false;
    }

    private function isRestful(): bool
    {
        if ($this->isAjaxRequest()) {
            return false;
        }

        $uri = $this->httpHeader->getRequestUri();

        return preg_match('/\/rest\//m', $uri);
    }

    private function isAjaxRequest(): bool
    {
        /** @var ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var RequestInterface|Http $request */
        $request = $om->get(RequestInterface::class);

        return $request->isXmlHttpRequest();
    }

    private function removeTamaraMethod($availableMethods): array
    {
        foreach ($availableMethods as $key => $method) {
            if (PaymentHelper::isTamaraPayment($method->getCode())) {
                unset($availableMethods[$key]);
            }
        }

        return $availableMethods;
    }

    private function filterUnderOverLimit($availableMethods, $price, $storeId) {
        $storeCurrency = $this->tamaraHelper->getStoreCurrencyCode($storeId);
        $paymentTypes = $this->tamaraHelper->getPaymentTypes(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrency],
            $storeCurrency, $storeId);
        foreach ($availableMethods as $key => $method) {
            $methodCode = $method->getCode();
            if (!PaymentHelper::isTamaraPayment($methodCode)) {
                continue;
            }
            if ($price < $paymentTypes[$methodCode]['min_limit'] || $price > $paymentTypes[$methodCode]['max_limit']) {
                unset($availableMethods[$key]);
            }
        }
        return $availableMethods;
    }
}
