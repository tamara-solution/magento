<?php

namespace Tamara\Checkout\Model\Helper;

class LocaleHelper
{
    public static function getLocale()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $store = $objectManager->get('Magento\Framework\Locale\Resolver');
        return $store->getLocale();
    }
}