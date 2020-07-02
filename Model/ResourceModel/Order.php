<?php

namespace Tamara\Checkout\Model\ResourceModel;

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

    public function getByTamaraOrderId($tamaraOrderId)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable(), 'tamara_id')
            ->where('tamara_order_id = :tamara_order_id');

        $bind = [':tamara_order_id' => $tamaraOrderId];

        return $connection->fetchOne($select, $bind);
    }
}