<?php

namespace Tamara\Checkout\Setup;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Cms\Model\BlockFactory;

class UpgradeSchema implements UpgradeSchemaInterface
{
    const TAMARA_WHITELIST = 'tamara_email_whitelist',
          TAMARA_CUSTOMER_WHITELIST = 'tamara_customer_whitelist',
          TAMARA_CAPTURE_ITEMS = 'tamara_capture_items';

    private $blockFactory;
    private $blockRepository;

    public function __construct(BlockFactory $blockFactory, BlockRepositoryInterface $blockRepository)
    {
        $this->blockFactory = $blockFactory;
        $this->blockRepository = $blockRepository;
    }


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

        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            $this->createCmsBlock();
        }

        if (version_compare($context->getVersion(), '1.0.6', '<')) {
            $this->updateCmsBlock();
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

    private function createCmsBlock(){
        $content = '<div class="modal">
                    <div class="modal-overlay modal-toggle">&nbsp;</div>
                    <div class="modal-wrapper modal-transition">
                    <div class="modal-header"><button class="modal-close modal-toggle"></button>
                    <div class="modal-heading"><img class="title" src="{{view url=\'Tamara_Checkout::images/logo.svg\'}}" alt="Tamara - buy now pay later">
                    <p style="padding: 8px 0">{{trans "Buy now pay later in 30 days"}}</p>
                    </div>
                    </div>
                    <div class="modal-body">
                    <div class="modal-content one-block">
                    <div class="left-content"><img src="{{view url=\'Tamara_Checkout::images/icon1.svg\'}}" alt=""></div>
                    <div class="right-content">
                    <p class="sub-title">{{trans "No fees"}}</p>
                    <p class="sub-description">{{trans "Zero interest and no hidden fees."}}</p>
                    </div>
                    </div>
                    <div class="modal-content one-block">
                    <div class="left-content"><img src="{{view url=\'Tamara_Checkout::images/icon2.svg\'}}" alt=""></div>
                    <div class="right-content">
                    <p class="sub-title">{{trans "No credit card? No problem!"}}</p>
                    <p class="sub-description">{{trans "Use any debit card or bank transfer to repay."}}</p>
                    </div>
                    </div>
                    <div class="modal-content one-block">
                    <div class="left-content"><img src="{{view url=\'Tamara_Checkout::images/icon3.svg\'}}" alt=""></div>
                    <div class="right-content">
                    <p class="sub-title">{{trans "Quick and easy"}}</p>
                    <p class="sub-description">{{trans "Simple use your phone number and complete your checkout."}}</p>
                    </div>
                    </div>
                    <div style="text-align: center">{{trans "Sounds good? Just select tamara at checkout."}}</div>
                    </div>
                    </div>
                    </div>';
        $cmsBlockData = [
            'title' => 'Tamara Checkout Info',
            'identifier' => 'tamara_cms_block_info',
            'content' => $content,
            'is_active' => 1,
            'stores' => [0],
            'sort_order' => 0
        ];

        $this->blockFactory->create()->setData($cmsBlockData)->save();
    }

    private function updateCmsBlock() {
        $cmsBlock = $this->blockRepository->getById('tamara_cms_block_info');
        $content = '<div class="modal">
                    <div class="modal-overlay modal-toggle">&nbsp;</div>
                    <div class="modal-wrapper modal-transition">
                    <div class="modal-header"><button class="modal-close modal-toggle"></button>
                    <div class="modal-heading"><img class="title" src="{{view url=\'Tamara_Checkout::images/logo.svg\'}}" alt="Tamara - buy now pay later">
                    <p class="sub-title-head">{{trans "Receive the good before you pay for it"}}</p>
                    <p style="padding: 8px 0">{{trans "Pay within 30 days after shipping."}}</p>
                    </div>
                    </div>
                    <div class="modal-body">
                    <div class="modal-content one-block">
                    <div class="left-content"><img src="{{view url=\'Tamara_Checkout::images/zero-percent.svg\'}}" alt=""></div>
                    <div class="right-content">
                    <p class="sub-title">{{trans "Zero Interest"}}</p>
                    <p class="sub-description">{{trans "No hidden fees."}}</p>
                    </div>
                    </div>
                    <div class="modal-content one-block">
                    <div class="left-content"><img src="{{view url=\'Tamara_Checkout::images/debit-card.svg\'}}" alt=""></div>
                    <div class="right-content">
                    <p class="sub-title">{{trans "No credit card needed"}}</p>
                    <p class="sub-description">{{trans "Use any debit card or bank transfer or even Apple pay to repay."}}</p>
                    </div>
                    </div>
                    <div class="modal-content one-block">
                    <div class="left-content"><img src="{{view url=\'Tamara_Checkout::images/mobile.svg\'}}" alt=""></div>
                    <div class="right-content">
                    <p class="sub-title">{{trans "Quick and easy"}}</p>
                    <p class="sub-description">{{trans "Simply, use your phone number once you complete your checkout."}}</p>
                    </div>
                    </div>
                    <div style="text-align: center">{{trans "Sounds good? Just select Tamara at checkout."}}</div>
                    <div style="text-align: center">{{trans "For more information about"}} <a target="_blank" href="https://tamara.co"> {{trans "Tamara"}} </a></div>
                    </div>
                    </div>
                    </div>
        ';

        $cmsBlock['content'] = $content;
        $this->blockRepository->save($cmsBlock);
    }


}