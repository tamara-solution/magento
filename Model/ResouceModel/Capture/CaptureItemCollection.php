<?php

namespace Tamara\Checkout\Model\ResouceModel\Capture;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\CaptureItem;

class CaptureItemCollection extends AbstractCollection
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct()
    {
        $this->_init(CaptureItem::class, \Tamara\Checkout\Model\ResouceModel\CaptureItem::class);
    }
}