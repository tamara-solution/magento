<?php

namespace Tamara\Checkout\Model\Method;

class TamaraCheckout extends \Tamara\Checkout\Model\Method\Checkout
{
    /**
     * @param null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return false;
    }
}