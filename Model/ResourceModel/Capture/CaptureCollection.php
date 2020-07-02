<?php

namespace Tamara\Checkout\Model\ResourceModel\Capture;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tamara\Checkout\Model\Capture;

class CaptureCollection extends AbstractCollection
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct()
    {
        $this->_init(Capture::class, \Tamara\Checkout\Model\ResourceModel\Capture::class);
    }
}