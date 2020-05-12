<?php

namespace Tamara\Checkout\Model\ResouceModel\EmailWhiteList;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\EmailWhiteList;

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
        $this->_init(EmailWhiteList::class, \Tamara\Checkout\Model\ResouceModel\EmailWhiteList::class);
    }

}