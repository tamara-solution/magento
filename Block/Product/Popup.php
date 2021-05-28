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

    protected $payLaterConfig;

    /**
     * @var \Tamara\Checkout\Helper\AbstractData
     */
    protected $tamaraHelper;

    public function __construct(
        Template\Context $context,
        \Magento\Framework\Registry $registry,
        BaseConfig $config,
        Session $customerSession,
        EmailWhiteListFactory $whiteListFactory,
        \Tamara\Checkout\Helper\AbstractData $helper,
        \Tamara\Checkout\Gateway\Config\InstalmentConfig $instalmentConfig,
        \Tamara\Checkout\Gateway\Config\PayLaterConfig $payLaterConfig,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
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
        $this->payLaterConfig = $payLaterConfig;
        $this->tamaraHelper = $tamaraHelper;
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
            return $currentProduct->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
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
     * @return bool
     */
    public function isEnabledPayLaterMethod() {
        return $this->payLaterConfig->isEnabled();
    }

    /**
     * @return bool
     */
    public function isEnabledInstallmentsMethod() {
        return $this->instalmentConfig->isEnabled();
    }

    /**
     * @param float $price
     * @return array
     */
    public function getAvailablePaymentMethods(float $price) {
        $enabledMethods = $this->getEnabledMethods();
        $inLimitMethods = $this->filterUnderOver($enabledMethods, $price);
        return $this->markHighPriorityMethod($inLimitMethods);
    }

    protected function markHighPriorityMethod(array $methods) {
        if (!empty($methods)) {
            $isExistedHighPriorityMethod = false;
            foreach ($methods as &$method) {
                if ($method['name'] == \Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS) {
                    $method['checked'] = true;
                    $isExistedHighPriorityMethod = true;
                } else {
                    $method['checked'] = false;
                }
            }
            if (!$isExistedHighPriorityMethod) {
                $methods[0]['checked'] = true;
            }
        }
        return $methods;
    }

    /**
     * @param array $methods
     * @param float $price
     * @return array
     */
    public function filterUnderOver(array $methods, float $price) {
        $result = [];
        foreach ($methods as $method) {
            if ($price < $method['min_limit'] || $price > $method['max_limit']) {
                continue;
            }
            $result[] = $method;
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getEnabledMethods() {
        $enabledMethods = [];
        if ($this->isEnabledPayLaterMethod()) {
            $enabledMethods[] = [
                'name' => \Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER,
                'min_limit' => $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]['min_limit'],
                'max_limit' => $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER]['max_limit']
            ];
        }
        if ($this->isEnabledInstallmentsMethod()) {
            $enabledMethods[] = [
                'name' => \Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS,
                'min_limit' => $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]['min_limit'],
                'max_limit' => $this->tamaraHelper->getPaymentTypesOfStore()[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS]['max_limit']
            ];
        }
        return $enabledMethods;
    }

    /**
     * @param $price
     * @return array|mixed
     */
    public function getPaymentMethodForPdpWidget($price) {
        $result = [];
        $availableMethods = $this->getAvailablePaymentMethods($price);
        if (!empty($availableMethods)) {
            foreach ($availableMethods as $method) {
                if ($method['checked']) {
                    return $method;
                }
            }
        }
        return $result;
    }
}
