<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

class AbstractData extends AbstractHelper
{
    protected $locale;
    protected $storeManager;

    public function __construct(
        Context $context,
        \Magento\Framework\Locale\Resolver $locale,
        StoreManagerInterface $storeManager
    ) {
        $this->locale = $locale;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @param $haystack string
     * @param $needle string
     * @return bool
     */
    public function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    /**
     * @return bool
     */
    public function isArabicLanguage()
    {
        return $this->startsWith($this->getLocale(), 'ar_');
    }

    /**
     * @param $haystack string
     * @param $needle string
     * @return bool
     */
    public function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return substr($haystack, 0, $length) === $needle;
    }

    /**
     * @return string|null
     */
    public function getLocale()
    {
        return $this->locale->getLocale();
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentStore()
    {
        return $this->storeManager->getStore();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreCurrencyCode() {
        return $this->getCurrentStore()->getCurrentCurrencyCode();
    }
}