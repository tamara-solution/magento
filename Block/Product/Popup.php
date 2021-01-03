<?php

namespace Tamara\Checkout\Block\Product;

use Magento\Framework\View\Element\Template;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Magento\Customer\Model\Session;
use Tamara\Checkout\Model\EmailWhiteListFactory;

class Popup extends Template
{
    private $registry;

    protected $customerSession;

    protected $config;

    protected $whitelistFactory;

    protected $helper;

    protected $instalmentConfig;

    public function __construct(
        Template\Context $context,
        \Magento\Framework\Registry $registry,
        BaseConfig $config,
        Session $customerSession,
        EmailWhiteListFactory $whiteListFactory,
        \Tamara\Checkout\Helper\AbstractData $helper,
        \Tamara\Checkout\Gateway\Config\InstalmentConfig $instalmentConfig,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->whitelistFactory = $whiteListFactory;
        $this->helper = $helper;
        $this->instalmentConfig = $instalmentConfig;
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

    /**
     * @return bool
     */
    public function isArabicLanguage(): bool
    {
        return $this->helper->isArabicLanguage();
    }

    /**
     * @return \Magento\Framework\Pricing\Price\PriceInterface
     * @throws \Exception
     */
    public function getCurrentProductPrice() {

        /**
         * @var $currentProduct \Magento\Catalog\Model\Product
         */
        if ($currentProduct = $this->getCurrentProduct()) {
            return $currentProduct->getPriceInfo()->getPrice('final_price')->getValue();
        }
        throw new \Exception(__('Cannot get current product price'));
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreCurrencyCode() {
        return $this->helper->getStoreCurrencyCode();
    }

    /**
     * @return mixed
     */
    public function getCurrentProduct() {
        return $this->registry->registry('current_product');
    }

    /**
     * @return mixed
     */
    public function getTamaraPayByInstalmentsMinLimit() {
        return $this->instalmentConfig->getMinLimit();
    }
}
