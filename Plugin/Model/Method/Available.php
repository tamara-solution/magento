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
        $removeMethods = [];
        $paymentTypes = $this->tamaraHelper->getPaymentTypesOfStore();
        if (!isset($paymentTypes[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER])) {
            $removeMethods[] = \Tamara\Checkout\Gateway\Config\PayLaterConfig::PAYMENT_TYPE_CODE;
        }
        if (!isset($paymentTypes[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS])) {
            $removeMethods[] = \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE;
        }
        $availableMethods = $this->removeMethod($availableMethods, $removeMethods);

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

        $userAgent = $this->httpHeader->getHttpUserAgent();
        if ($this->config->isBlockWebViewEnabled()) {
            if (!$this->isWebView($userAgent) || $this->isRestful()) {
                return $this->removeTamaraMethod($availableMethods);
            }
        }

        if (!$this->config->getIsUseWhitelist()) {
            return $availableMethods;
        }

        $email = $quote->getCustomer()->getEmail();

        if ($email && $this->emailWhiteListRepository->isEmailWhitelisted($email)) {
            return $availableMethods;
        }

        return $this->removeTamaraMethod($availableMethods);
    }

    private function removeMethod($availableMethods, $removeMethods)
    {
        foreach ($availableMethods as $key => $method) {
            if (in_array($method->getCode(), $removeMethods)) {
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
}
