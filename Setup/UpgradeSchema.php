<?php

namespace Tamara\Checkout\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;


class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->createTamaraWhitelistTable($setup);
        }

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $this->addImageUrlColumnToCaptureItems($setup);
        }

        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            $this->addIndexForTables($setup);
        }

        if (version_compare($context->getVersion(), '1.0.4', '<')) {
            $this->createTamaraCustomerWhitelistTable($setup);
        }

        if (version_compare($context->getVersion(), '1.0.7', '<')) {
            $this->updateDataType($setup);
        }

        if (version_compare($context->getVersion(), '1.0.8', '<')) {
            $this->addProcessFromConsoleColumns($setup);
        }

        if (version_compare($context->getVersion(), '1.0.9', '<')) {
            $this->addConsoleQueryIndex($setup);
        }

        if (version_compare($context->getVersion(), '1.1.6', '<')) {
            $this->addPaymentTypeColumn($setup);
        }

        $setup->endSetup();
    }

    private function createTamaraWhitelistTable(SchemaSetupInterface $setup)
    {
        $fullWhiteListTableName = $setup->getTable(InstallSchema::TABLE_WHITELIST);
        $table = $setup->getConnection()->newTable($fullWhiteListTableName);
        $table->addColumn(
            'customer_email',
            Table::TYPE_TEXT,
            255,
            [
                'primary' => true,
                'nullable' => false
            ]
        )->addIndex(
            $setup->getIdxName(
                $fullWhiteListTableName,
                ['customer_email'],
                AdapterInterface::INDEX_TYPE_PRIMARY
            ),
            ['customer_email']
        );
        $setup->getConnection()->createTable($table);
        echo "Created table {$fullWhiteListTableName} \n";
    }

    private function addImageUrlColumnToCaptureItems(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable(InstallSchema::TABLE_CAPTURE_ITEMS),
            'image_url',
            [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '255',
                'comment' => 'store image url of item',
                'after' => 'name'
            ]
        );
    }

    private function addIndexForTables(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addIndex(
            $setup->getTable(InstallSchema::TABLE_ORDERS),
            'tamara_orders_tamara_order_id',
            ['tamara_order_id']
        );

        $setup->getConnection()->addIndex(
            $setup->getTable(InstallSchema::TABLE_ORDERS),
            'tamara_orders_order_id',
            ['order_id']
        );

        $setup->getConnection()->addIndex(
            $setup->getTable(InstallSchema::TABLE_CAPTURES),
            'tamara_captures_order_id',
            ['order_id']
        );

        $setup->getConnection()->addIndex(
            $setup->getTable(InstallSchema::TABLE_CAPTURE_ITEMS),
            'tamara_capture_items_order_id',
            ['order_id']
        );

        $setup->getConnection()->addIndex(
            $setup->getTable(InstallSchema::TABLE_CANCELS),
            'tamara_cancels_order_id',
            ['order_id']
        );

        $setup->getConnection()->addIndex(
            $setup->getTable(InstallSchema::TABLE_REFUNDS),
            'tamara_refunds_order_id',
            ['order_id']
        );
    }

    private function createTamaraCustomerWhitelistTable(SchemaSetupInterface $setup)
    {
        $fullCustomerWhiteListTableName = $setup->getTable(InstallSchema::TABLE_CUSTOMER_WHITELIST);
        $table = $setup->getConnection()->newTable($fullCustomerWhiteListTableName);

        $table->addColumn(
            'whitelist_id',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'primary' => true,
                'nullable' => false
            ]
        )->addColumn(
            'customer_email',
            Table::TYPE_TEXT,
            255,
            [
                'primary' => true,
                'nullable' => false
            ]
        )->addColumn(
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
                    $fullCustomerWhiteListTableName,
                    ['customer_email'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['customer_email'],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            );

        $setup->getConnection()->createTable($table);
        echo "Created table {$fullCustomerWhiteListTableName} \n";

        $sql = 'insert into ' . $fullCustomerWhiteListTableName . '(customer_email) select customer_email from ' . $setup->getTable(InstallSchema::TABLE_WHITELIST);

        $setup->getConnection()->query($sql);
        $setup->getConnection()->dropTable($setup->getTable(InstallSchema::TABLE_WHITELIST));
    }

    private function updateDataType(SchemaSetupInterface $setup)
    {
        $tables = [
            InstallSchema::TABLE_CAPTURE_ITEMS => [
                'unit_price' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Unit price'
                ],
                'total_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Total item amount'
                ],
                'tax_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Tax amount'
                ],
                'discount_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Discount amount'
                ],
            ],
            InstallSchema::TABLE_CAPTURES => [
                'total_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Total amount'
                ],
                'tax_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Tax amount'
                ],
                'shipping_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Shipping amount'
                ],
                'discount_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Discount amount'
                ],
                'refunded_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Refunded amount'
                ],
            ],
            InstallSchema::TABLE_REFUNDS => [
                'total_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Total amount'
                ],
                'refunded_amount' => [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'nullable' => true,
                    'default' => 0.00,
                    'comment' => 'Refunded amount'
                ],
            ],
        ];

        foreach ($tables as $tableName => $columns) {
            foreach ($columns as $columnName => $definition) {
                $setup->getConnection()->changeColumn(
                    $setup->getTable($tableName),
                    $columnName,
                    $columnName,
                    $definition
                );
            }
        }
    }

    private function addProcessFromConsoleColumns(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $columns = [
            'captured_from_console' => [
                'type' => Table::TYPE_BOOLEAN,
                'nullable' => false,
                'default' => false,
                'comment' => __('Captured from console')
            ],
            'canceled_from_console' => [
                'type' => Table::TYPE_BOOLEAN,
                'nullable' => false,
                'default' => false,
                'comment' => __('Canceled from console')
            ],
            'refunded_from_console' => [
                'type' => Table::TYPE_BOOLEAN,
                'nullable' => false,
                'default' => false,
                'comment' => __('Refunded from console')
            ]
        ];

        foreach ($columns as $columnName => $definition) {
            $connection->addColumn($setup->getTable(InstallSchema::TABLE_ORDERS), $columnName, $definition);
        }
    }

    private function addConsoleQueryIndex(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->query("ALTER TABLE {$setup->getTable(InstallSchema::TABLE_ORDERS)} ADD INDEX idx_console_query (is_authorised, created_at)");
    }

    private function addPaymentTypeColumn(SchemaSetupInterface $setup) {
        $connection = $setup->getConnection();
        $columns = [
            'payment_type' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'LENGTH' => 255,
                'comment' => __('Payment type')
            ],
            'number_of_installments' => [
                'type' => Table::TYPE_INTEGER,
                'nullable' => true,
                'comment' => __('Payment type')
            ]
        ];

        foreach ($columns as $columnName => $definition) {
            if (!$connection->tableColumnExists($setup->getTable(InstallSchema::TABLE_ORDERS), $columnName)) {
                $connection->addColumn($setup->getTable(InstallSchema::TABLE_ORDERS), $columnName, $definition);
            }
        }
    }
}