<?php

namespace Tamara\Checkout\Model\ResouceModel\Cancel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\Cancel;

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
        $this->_init(Cancel::class, \Tamara\Checkout\Model\ResouceModel\Cancel::class);
    }
}