<?php

namespace Tamara\Checkout\Block;

use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Template;
use Tamara\Checkout\Gateway\Config\BaseConfig;

class PopulateTamara extends Template
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
        $output['tamaraLogoImageUrl'] = $this->getViewFileUrl('Tamara_Checkout::images/tamara_logo.svg');
        $output['tamaraCartLogo'] = $this->getViewFileUrl('Tamara_Checkout::images/cart.svg');
        $output['tamaraAboutLink'] = $this->config->getLinkAboutTamara();
        return $output;
    }

    public function getViewFileUrl($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->assetRepository->getUrlWithParams($fileId, $params);
    }
}