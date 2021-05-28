<?php

namespace Tamara\Checkout\Plugin\Theme\Model;

class Theme
{
    protected $tamaraHelper;

    public function __construct(
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper
    ) {
        $this->tamaraHelper = $tamaraHelper;
    }

    public function aroundGetArea(\Magento\Theme\Model\Theme $subject, callable $proceed)
    {
        try {
            $result = $proceed();
            return $result;
        } catch (\Exception $exception) {
            $this->tamaraHelper->getLogger()->debug(["Tamara - Cannot get area code by magento, return frontend"]);
            return \Magento\Framework\App\Area::AREA_FRONTEND;
        }
    }
}
