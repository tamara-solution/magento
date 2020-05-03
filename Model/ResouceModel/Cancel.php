<?php

namespace Tamara\Checkout\Model\ResouceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Cancel extends AbstractDb
{
    protected $_isPkAutoIncrement = false;

    protected function _construct()
    {
        $this->_init('tamara_cancels', 'cancel_id');
    }
}