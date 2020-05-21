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
}