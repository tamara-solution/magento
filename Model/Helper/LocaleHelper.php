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

    public static function getCurrentLanguage()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $store = $objectManager->get('Magento\Framework\Locale\Resolver');
        $currentLocale = $store->getLocale();
        return strstr($currentLocale, '_', true);
    }
}