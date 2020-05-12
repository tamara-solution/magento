<?php

namespace Tamara\Checkout\Plugin\Model\Method;

use Magento\Payment\Model\Method\Logger;
use Tamara\Checkout\Api\EmailWhiteListRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\PaymentHelper;

class Available
{
    private $logger;

    private $config;

    private $emailWhiteListRepository;

    public function __construct(
        Logger $logger,
        BaseConfig $config,
        EmailWhiteListRepositoryInterface $emailWhiteListRepository
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->emailWhiteListRepository = $emailWhiteListRepository;
    }


    public function afterGetAvailableMethods(
        \Magento\Payment\Model\MethodList $subject,
        $availableMethods,
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        if (!$this->config->getIsUseWhitelist()) {
            return $availableMethods;
        }

        $email = $quote->getCustomer()->getEmail();

        if ($email === null) {
            return $availableMethods;
        }

        if ($this->emailWhiteListRepository->isEmailWhitelisted($email)) {
            return $availableMethods;
        }

        foreach ($availableMethods as $key => $method) {
            if (PaymentHelper::isTamaraPayment($method->getCode())) {
                unset($availableMethods[$key]);
            }
        }
        return $availableMethods;
    }
}