<?php

namespace Tamara\Checkout\Plugin\Magento\Framework\View\Page\Config;

use Magento\Framework\View\Page\Config\Renderer;

class DisableMixinJs
{

    private $assetRepo;
    private $pageAssets;
    private $tamaraConfig;

    public function __construct(
        \Magento\Framework\View\Asset\Repository        $assetRepo,
        \Magento\Framework\View\Asset\GroupedCollection $pageAssets,
        \Tamara\Checkout\Gateway\Config\BaseConfig      $tamaraConfig
    )
    {
        $this->assetRepo = $assetRepo;
        $this->pageAssets = $pageAssets;
        $this->tamaraConfig = $tamaraConfig;
    }

    public function beforeRenderAssets(Renderer $subject, $resultGroups = [])
    {
        if (!$this->tamaraConfig->isEnableTamaraPayment() && !$this->tamaraConfig->getTamaraCore()->isAdminArea()) {
            $file = 'Tamara_Checkout::js/paymentDisabled.js';
            $asset = $this->assetRepo->createAsset($file);
            $this->pageAssets->insert($file, $asset, 'requirejs/require.js');
            return [$resultGroups];
        }
    }
}