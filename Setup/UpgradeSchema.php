<?php

namespace Tamara\Checkout\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    const TAMARA_WHITELIST = 'tamara_email_whitelist';

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $setup->startSetup();

            $this->createTamaraWhitelistTable($setup);

            $setup->endSetup();
        }
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