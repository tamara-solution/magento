<?php

namespace Tamara\Checkout\Model\ResourceModel\Refund;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\Refund;

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
        $this->_init(Refund::class, \Tamara\Checkout\Model\ResourceModel\Refund::class);
    }
}