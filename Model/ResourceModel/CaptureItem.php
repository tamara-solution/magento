<?php

namespace Tamara\Checkout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CaptureItem extends AbstractDb
{

    protected $_isPkAutoIncrement = false;

    protected function _construct()
    {
        $this->_init('tamara_capture_items', 'order_item_id');
    }

    public function getByConditions(array $conditions)
    {
        $connection = $this->getConnection();
        $bind = [];

        $select = $connection->select()->from($this->getMainTable());

        if (isset($conditions['order_id'])) {
            $select->where('order_id = :order_id');
            $bind[':order_id'] = $conditions['order_id'];
        }

        if (isset($conditions['order_item_id'])) {
            $select->where('order_item_id = :order_item_id');
            $bind[':order_item_id'] = $conditions['order_item_id'];
        }

        if (isset($conditions['capture_id'])) {
            $select->where('capture_id = :capture_id');
            $bind[':capture_id'] = $conditions['capture_id'];
        }

        $select->order('created_at ASC');

        return $connection->fetchAll($select, $bind);
    }
}