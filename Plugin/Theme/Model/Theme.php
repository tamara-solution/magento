<?php

namespace Tamara\Checkout\Plugin\Theme\Model;

class Theme
{
    public function aroundGetArea(\Magento\Theme\Model\Theme $subject, callable $proceed)
    {
        try {
            $result = $proceed();
            return $result;
        } catch (\Exception $exception) {
            return \Magento\Framework\App\Area::AREA_FRONTEND;
        }
    }
}
