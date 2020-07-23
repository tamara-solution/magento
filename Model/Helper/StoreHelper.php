<?php

declare(strict_types=1);

namespace Tamara\Checkout\Model\Helper;

class StoreHelper
{
    public static function getBaseUrl()
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $store = $storeManager->getStore();
        return $store->getBaseUrl();
    }
}