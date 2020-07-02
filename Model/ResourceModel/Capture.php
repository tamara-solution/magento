<?php

namespace Tamara\Checkout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Capture extends AbstractDb
{
    protected $_isPkAutoIncrement = false;

    protected function _construct()
    {
        $this->_init('tamara_captures', 'capture_id');
    }

    public function getByConditions(array $conditions)
    {
        $connection = $this->getConnection();
        $bind =[];

        $select = $connection->select()->from($this->getMainTable());

        if (isset($conditions['order_id'])) {
            $select->where('order_id = :order_id');
            $bind[':order_id'] = $conditions['order_id'];
        }

        if (isset($conditions['capture_id'])) {
            $select->where('capture_id = :capture_id');
            $bind[':capture_id'] = $conditions['capture_id'];
        }

        $select->order('created_at ASC');

        return $connection->fetchAll($select, $bind);
    }

    public function getByCaptureId($captureId)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable(), 'capture_id')
            ->where('capture_id = :capture_id');

        $bind = [':capture_id' => $captureId];

        return $connection->fetchOne($select, $bind);
    }
}