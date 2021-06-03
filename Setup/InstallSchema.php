<?php

namespace Tamara\Checkout\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public const
        TABLE_ORDERS = 'tamara_orders',
        TABLE_CAPTURES = 'tamara_captures',
        TABLE_CAPTURE_ITEMS = 'tamara_capture_items',
        TABLE_REFUNDS = 'tamara_refunds',
        TABLE_CANCELS = 'tamara_cancels',
        TABLE_WHITELIST = 'tamara_email_whitelist',
        TABLE_CUSTOMER_WHITELIST = 'tamara_customer_whitelist';

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $this->createTableOrders($setup);
        $this->createTableCaptures($setup);
        $this->createCaptureItems($setup);
        $this->createRefundTable($setup);
        $this->createCancelTable($setup);

        $setup->endSetup();
    }

    private function createTableOrders(SchemaSetupInterface $setup)
    {
        $fullOrdersTableName = $setup->getTable(self::TABLE_ORDERS);
        $table = $setup->getConnection()->newTable($fullOrdersTableName);

        $table
            ->addColumn(
                'tamara_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'nullable' => false
                ]
            )->addColumn(
                'tamara_order_id',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false
                ]
            )
            ->addColumn(
                'redirect_url',
                Table::TYPE_TEXT,
                255
            )
            ->addColumn(
                'is_authorised',
                Table::TYPE_SMALLINT,
                null,
                [
                    'nullable' => false,
                    'default' => 0
                ]
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                'Created At'
            )->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Updated At')
            ->addIndex(
                $setup->getIdxName(
                    $fullOrdersTableName,
                    ['tamara_id'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['tamara_id', 'order_id', 'tamara_order_id']
            );

        $setup->getConnection()->createTable($table);
    }

    private function createTableCaptures(SchemaSetupInterface $setup)
    {
        $fullCapturesTableName = $setup->getTable(self::TABLE_CAPTURES);
        $table = $setup->getConnection()->newTable($fullCapturesTableName);

        $table
            ->addColumn(
                'capture_id',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'primary' => true,
                    'nullable' => false
                ]
            )->addColumn(
                'tamara_order_id',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'total_amount',
                Table::TYPE_FLOAT,
                [
                    'default' => 0
                ]
            )
            ->addColumn(
                'tax_amount',
                Table::TYPE_FLOAT,
                [
                    'default' => 0
                ]
            )
            ->addColumn(
                'shipping_amount',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'discount_amount',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'refunded_amount',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'shipping_info',
                Table::TYPE_TEXT,
                null,
                [
                    'nullable' => true
                ]
            )
            ->addColumn(
                'currency',
                Table::TYPE_TEXT,
                3
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                'Created At'
            )->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Updated At')
            ->addIndex(
                $setup->getIdxName(
                    $fullCapturesTableName,
                    ['capture_id'],
                    AdapterInterface::INDEX_TYPE_PRIMARY
                ),
                ['tamara_order_id', 'order_id', 'capture_id']
            );

        $setup->getConnection()->createTable($table);
    }

    private function createCaptureItems(SchemaSetupInterface $setup)
    {
        $fullCaptureItemsTableName = $setup->getTable(self::TABLE_CAPTURE_ITEMS);
        $table = $setup->getConnection()->newTable($fullCaptureItemsTableName);

        $table
            ->addColumn(
                'order_item_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'capture_id',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'name',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false
                ]
            )
            ->addColumn(
                'sku',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false
                ]
            )
            ->addColumn(
                'type',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false
                ]
            )
            ->addColumn(
                'quantity',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'unit_price',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'total_amount',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'tax_amount',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'discount_amount',
                Table::TYPE_FLOAT,
                ['default' => 0]
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                'Created At'
            )->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Updated At')
            ->addIndex(
                $setup->getIdxName(
                    $fullCaptureItemsTableName,
                    ['order_item_id'],
                    AdapterInterface::INDEX_TYPE_PRIMARY
                ),
                ['order_item_id', 'order_id', 'capture_id']
            );

        $setup->getConnection()->createTable($table);
    }

    private function createRefundTable(SchemaSetupInterface $setup)
    {
        $fullRefundsTableName = $setup->getTable(self::TABLE_REFUNDS);
        $table = $setup->getConnection()->newTable($fullRefundsTableName);

        $table->addColumn(
            'refund_id',
            Table::TYPE_TEXT,
            255,
            [
                'primary' => true,
                'nullable' => false
            ]
        )
            ->addColumn(
                'capture_id',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'primary' => true,
                    'nullable' => false
                ]
            )->addColumn(
                'tamara_order_id',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'total_amount',
                Table::TYPE_FLOAT,
                [
                    'default' => 0
                ]
            )
            ->addColumn(
                'refunded_amount',
                Table::TYPE_FLOAT,
                [
                    'default' => 0
                ]
            )
            ->addColumn(
                'currency',
                Table::TYPE_TEXT,
                3
            )
            ->addColumn(
                'request',
                Table::TYPE_TEXT,
                null,
                [
                    'nullable' => true
                ]
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                'Created At'
            )->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Updated At')
            ->addIndex(
                $setup->getIdxName(
                    $fullRefundsTableName,
                    ['capture_id'],
                    AdapterInterface::INDEX_TYPE_PRIMARY
                ),
                ['tamara_order_id', 'order_id', 'capture_id', 'refund_id']
            );

        $setup->getConnection()->createTable($table);
    }

    private function createCancelTable(SchemaSetupInterface $setup)
    {
        $fullCancelsTableName = $setup->getTable(self::TABLE_CANCELS);
        $table = $setup->getConnection()->newTable($fullCancelsTableName);

        $table->addColumn(
            'cancel_id',
            Table::TYPE_TEXT,
            255,
            [
                'primary' => true,
                'nullable' => false
            ]
        )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'primary' => true,
                    'nullable' => false
                ]
            )->addColumn(
                'tamara_order_id',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'request',
                Table::TYPE_TEXT,
                null,
                [
                    'nullable' => true
                ]
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                'Created At'
            )->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                'Updated At')
            ->addIndex(
                $setup->getIdxName(
                    $fullCancelsTableName,
                    ['capture_id'],
                    AdapterInterface::INDEX_TYPE_PRIMARY
                ),
                ['tamara_order_id', 'order_id', 'cancel_id']
            );

        $setup->getConnection()->createTable($table);
    }

}