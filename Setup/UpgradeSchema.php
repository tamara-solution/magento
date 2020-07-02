<?php

namespace Tamara\Checkout\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    const TAMARA_WHITELIST = 'tamara_email_whitelist',
          TAMARA_CUSTOMER_WHITELIST = 'tamara_customer_whitelist',
          TAMARA_CAPTURE_ITEMS = 'tamara_capture_items';

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->createTamaraWhitelistTable($setup);
        }

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $setup->getConnection()->addColumn(
                $setup->getTable(self::TAMARA_CAPTURE_ITEMS ),
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

        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            $setup->getConnection()->addIndex(
                'tamara_orders',
                'tamara_orders_tamara_order_id',
                ['tamara_order_id']
            );

            $setup->getConnection()->addIndex(
                'tamara_orders',
                'tamara_orders_order_id',
                ['order_id']
            );

            $setup->getConnection()->addIndex(
                'tamara_captures',
                'tamara_captures_order_id',
                ['order_id']
            );

            $setup->getConnection()->addIndex(
                'tamara_capture_items',
                'tamara_capture_items_order_id',
                ['order_id']
            );

            $setup->getConnection()->addIndex(
                'tamara_cancels',
                'tamara_cancels_order_id',
                ['order_id']
            );

            $setup->getConnection()->addIndex(
                'tamara_refunds',
                'tamara_refunds_order_id',
                ['order_id']
            );
        }

        if (version_compare($context->getVersion(), '1.0.4', '<')) {
            $this->createTamaraCustomerWhitelistTable($setup);
            $setup->getConnection()->query('DROP TABLE ' . self::TAMARA_WHITELIST);
        }

        $setup->endSetup();
    }

    private function createTamaraWhitelistTable(SchemaSetupInterface $setup)
    {
        if ($setup->tableExists(self::TAMARA_WHITELIST)) {
            return;
        }

        $table = $setup->getConnection()->newTable($setup->getTable(self::TAMARA_WHITELIST));

        $table->addColumn(
            'customer_email',
            Table::TYPE_TEXT,
            255,
            [
                'primary' => true,
                'nullable' => false
            ]
        )
            ->addIndex(
                $setup->getIdxName(
                    self::TAMARA_WHITELIST,
                    ['customer_email'],
                    AdapterInterface::INDEX_TYPE_PRIMARY
                ),
                ['customer_email']
            );

        $setup->getConnection()->createTable($table);
    }

    private function createTamaraCustomerWhitelistTable(SchemaSetupInterface $setup)
    {
        if ($setup->tableExists(self::TAMARA_CUSTOMER_WHITELIST)) {
            return;
        }

        $table = $setup->getConnection()->newTable($setup->getTable(self::TAMARA_CUSTOMER_WHITELIST));

        $table->addColumn(
            'whitelist_id',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'primary'  => true,
                'nullable' => false
            ]
        )
            ->addColumn(
                'customer_email',
                Table::TYPE_TEXT,
                255,
                [
                    'primary' => true,
                    'nullable' => false
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
                    self::TAMARA_CUSTOMER_WHITELIST,
                    ['customer_email'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['customer_email'],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            );

        $setup->getConnection()->createTable($table);

        $sql = 'insert into ' . self::TAMARA_CUSTOMER_WHITELIST . '(customer_email) select customer_email from ' . self::TAMARA_WHITELIST;

        $setup->getConnection()->query($sql);
    }
}