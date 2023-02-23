<?php

namespace Tamara\Checkout\Block;

use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Template;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\LocaleHelper;

class PopulateTamara extends Template
{
    protected $assetRepository;

    protected $config;

    protected $helper;

    public function __construct(
        Template\Context $context,
        AssetRepository $assetRepository,
        BaseConfig $config,
        \Tamara\Checkout\Helper\AbstractData $helper
    ){
        parent::__construct($context);
        $this->assetRepository = $assetRepository;
        $this->config = $config;
        $this->helper = $helper;
    }

    public function getTamaraConfig() {
        $tamaraLogo = sprintf('https://cdn.tamara.co/assets/svg/tamara-know-more-%s.svg', LocaleHelper::getCurrentLanguage());
        $output['tamaraLogoImageUrl'] = $tamaraLogo;
        $output['tamaraCartLogo'] = $this->getViewFileUrl('Tamara_Checkout::images/cart.svg');
        $output['tamaraAboutLink'] = $this->config->getLinkAboutTamara();
        $output['tamaraBadgeUrl'] = sprintf('https://cdn.tamara.co/assets/svg/tamara-logo-badge-%s.svg', LocaleHelper::getCurrentLanguage());
        $currencyCode = $this->helper->getStoreCurrencyCode();
        if (!empty(\Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$currencyCode])){
            $output['tamaraCountryCode'] = \Tamara\Checkout\Gateway\Validator\CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$currencyCode];
        } else {
            $output['tamaraCountryCode'] = "";
        }
        return $output;
    }

    public function getViewFileUrl($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->assetRepository->getUrlWithParams($fileId, $params);
    }

    /**
     * @return bool
     */
    public function isArabicLanguage() {
        if ($this->helper->startsWith($this->helper->getLocale(), 'ar')) {
            return true;
        }
        return false;
    }

    public function isProductionEnvironment() {
        return $this->config->isProductionApiEnvironment();
    }

    /**
     * @return bool
     */
    public function isUseWidgetV1()
    {
        return empty($this->helper->getMerchantPublicKey());
    }

    public function getWidgetVersion() {
        return $this->helper->getWidgetVersion();
    }
}