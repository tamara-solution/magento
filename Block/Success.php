<?php

namespace Tamara\Checkout\Block;

use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Template;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\LocaleHelper;

class Success extends Template
{
    protected $assetRepository;

    protected $config;

    public function __construct(
        Template\Context $context,
        AssetRepository $assetRepository,
        BaseConfig $config
    ){
        parent::__construct($context);
        $this->assetRepository = $assetRepository;
        $this->config = $config;
    }

    public function getTamaraConfig() {
        $successLogo = sprintf('Tamara_Checkout::images/success_%s.svg', LocaleHelper::getCurrentLanguage());
        $output['tamaraSuccessLogo'] = $this->getViewFileUrl($successLogo);
        $output['tamaraLoginLink'] = $this->config->getLinkLoginTamara(). '?locale=' . LocaleHelper::getLocale();
        return $output;
    }

    public function getViewFileUrl($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->assetRepository->getUrlWithParams($fileId, $params);
    }
}