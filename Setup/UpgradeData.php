<?php

namespace Tamara\Checkout\Setup;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{

    /**
     * @var BlockFactory
     */
    private $blockFactory;

    /**
     * @var BlockRepositoryInterface
     */
    private $blockRepository;

    public function __construct(BlockFactory $blockFactory, BlockRepositoryInterface $blockRepository)
    {
        $this->blockFactory = $blockFactory;
        $this->blockRepository = $blockRepository;
    }

    /**
     * Upgrades data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            $this->createCmsBlock();
        }

        if (version_compare($context->getVersion(), '1.0.6', '<')) {
            $this->updateCmsBlock();
        }

        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $this->removeCmsBlock();
        }

        $setup->endSetup();
    }

    private function createCmsBlock()
    {
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

    private function updateCmsBlock()
    {
        $cmsBlock = $this->blockRepository->getById('tamara_cms_block_info');
        $content = '<div class="modal">
                    <div class="modal-overlay modal-toggle">&nbsp;</div>
                    <div class="modal-wrapper modal-transition">
                    <div class="modal-header"><button class="modal-close modal-toggle"></button>
                    <div class="modal-heading"><img class="title" src="{{view url=\'Tamara_Checkout::images/\'}}/{{trans \'logo.svg\'}}" alt="Tamara - buy now pay later">
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

    private function removeCmsBlock()
    {
        $cmsBlock = $this->blockRepository->getById('tamara_cms_block_info');
        $this->blockRepository->deleteById($cmsBlock->getId());
    }
}