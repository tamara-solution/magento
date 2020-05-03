<?php

namespace Tamara\Checkout\Model\ResouceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Order extends AbstractDb
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct()
    {
        $this->_init('tamara_orders', 'tamara_id');
    }

    public function getByOrderId($id)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable(), 'tamara_id')
            ->where('order_id = :order_id');

        $bind = [':order_id' => $id];

        return $connection->fetchOne($select, $bind);
    }
}