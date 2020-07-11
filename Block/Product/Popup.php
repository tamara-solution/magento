<?php

namespace Tamara\Checkout\Block\Product;

use Magento\Framework\View\Element\Template;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Magento\Customer\Model\Session;
use Tamara\Checkout\Model\EmailWhiteListFactory;

class Popup extends Template
{
    const SA_LANGUAGE = 'ar_SA';
    protected $config;

    protected $customerSession;

    protected $whitelistFactory;

    protected $_store;

    public function __construct(
        Template\Context $context,
        BaseConfig $config,
        Session $customerSession,
        EmailWhiteListFactory $whiteListFactory,
        \Magento\Framework\Locale\Resolver $store,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->whitelistFactory = $whiteListFactory;
        $this->_store = $store;
    }

    protected function _toHtml()
    {
        return parent::_toHtml();
    }

    public function isShowPopup(): bool
    {
        $whitelistConfig = $this->config->getIsUseWhitelist();
        if (!$whitelistConfig) {
            return true;
        }
        return $this->isAllowWhitelistEmail();


    }

    private function isAllowWhitelistEmail(): bool
    {
        $isLogin = $this->customerSession->isLoggedIn();
        if (!$isLogin) {
            return false;
        }
        $customerEmail = $this->customerSession->getCustomer()->getEmail();
        return $this->isWhiteListEmail($customerEmail);
    }

    private function isWhiteListEmail($customerEmail): bool
    {
        $model = $this->whitelistFactory->create();
        $collections = $model->getCollection()->addFieldToFilter('customer_email', $customerEmail)->getFirstItem();

        return $collections->getId() > 0;
    }

    public function isSaLanguage(): bool
    {
        $currentStore = $this->_store->getLocale();
        if ($currentStore === self::SA_LANGUAGE) {
            return true;
        }
        return false;
    }
}
