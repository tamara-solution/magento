<?php

namespace Tamara\Checkout\Model\ResouceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class EmailWhiteList extends AbstractDb
{
    protected $_isPkAutoIncrement = false;

    protected function _construct()
    {
        $this->_init('tamara_email_whitelist', 'customer_email');
    }

    public function getWhitelistedEmail($email)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable(), 'customer_email')
            ->where('customer_email = :email');

        $bind = [':email' => $email];

        return $connection->fetchOne($select, $bind);
    }
}