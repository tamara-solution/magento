<?php

namespace Tamara\Checkout\Model\ResourceModel\EmailWhiteList;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\EmailWhiteList;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'whitelist_id';
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct()
    {
        $this->_init(EmailWhiteList::class, \Tamara\Checkout\Model\ResourceModel\EmailWhiteList::class);
    }

}