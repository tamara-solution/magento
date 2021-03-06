<?php

namespace Tamara\Checkout\Model\ResouceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\Order;

class Collection extends AbstractCollection
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct()
    {
        $this->_init(Order::class, \Tamara\Checkout\Model\ResouceModel\Order::class);
    }
}