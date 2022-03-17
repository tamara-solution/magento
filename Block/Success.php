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

    protected $tamaraOrderRepository;

    public function __construct(
        Template\Context $context,
        AssetRepository $assetRepository,
        BaseConfig $config,
        \Tamara\Checkout\Api\OrderRepositoryInterface $tamaraOrderRepository
    ){
        parent::__construct($context);
        $this->assetRepository = $assetRepository;
        $this->config = $config;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
    }

    public function getTamaraConfig() {
        $orderId = $this->getData('order_id');
        $tamaraOrderId = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId)->getTamaraOrderId();
        $successLogo = sprintf('https://cdn.tamara.co/assets/svg/tamara-logo-badge-%s.svg', LocaleHelper::getCurrentLanguage());
        $output['tamaraSuccessLogo'] = $successLogo;
        $output['tamaraLoginLink'] = $this->config->getLinkLoginTamara() . '/orders/' . $tamaraOrderId . '?locale=' . LocaleHelper::getLocale();
        $output['order_increment_id'] = $this->getData('order_increment_id');
        return $output;
    }

    public function getViewFileUrl($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->assetRepository->getUrlWithParams($fileId, $params);
    }
}