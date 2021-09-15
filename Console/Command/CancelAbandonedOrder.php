<?php

namespace Tamara\Checkout\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;

class CancelAbandonedOrder extends Command
{

    const CANCEL_BEFORE = 'cancel-before';
    const STORE_ID = 'store_id';

    /**
     * @var \Tamara\Checkout\Helper\AbstractData
     */
    protected $helper;

    /**
     * @var BaseConfig
     */
    protected $config;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var \Tamara\Checkout\Model\ResourceModel\Order\CollectionFactory
     */
    private $tamaraOrderCollectionFactory;

    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\Registry $coreRegistry,
        \Tamara\Checkout\Helper\AbstractData $helper,
        \Tamara\Checkout\Model\ResourceModel\Order\CollectionFactory $tamaraOrderCollectionFactory,
        BaseConfig $config,
        string $name = null
    ) {
        $this->state = $state;
        $this->coreRegistry = $coreRegistry;
        $this->helper = $helper;
        $this->tamaraOrderCollectionFactory = $tamaraOrderCollectionFactory;
        $this->config = $config;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName("tamara:orders-cancel-abandoned");
        $this->setDescription("Cancel abandoned order");
        $this->addOption(
            self::CANCEL_BEFORE,
            null,
            InputOption::VALUE_OPTIONAL,
            'Cancel abandoned order before this time',
            '-40 minutes'
        );
        $this->addOption(
            self::STORE_ID,
            null,
            InputOption::VALUE_OPTIONAL,
            'store id',
            '0'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->prepare($input, $output);
        $this->process();
    }

    protected function prepare(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $exception) {
            //nothing
        }

        $this->input = $input;
        $this->output = $output;
        $this->helper->setOutput($output);
    }

    protected function process()
    {
        $this->helper->log(["Run cancel abandoned orders"]);
        $this->cancelOrders($this->input->getOption(self::CANCEL_BEFORE), $this->input->getOption(self::STORE_ID));
        $this->helper->log(["Done"]);
    }

    protected function cancelOrders($beforeTime, $storeId)
    {
        $beforeTime = gmdate('Y-m-d H:i:s', strtotime($beforeTime));
        $tamaraOrderCollection = $this->tamaraOrderCollectionFactory->create();
        $salesOrderTable = $tamaraOrderCollection->getConnection()->getTableName('sales_order');
        $tamaraOrderCollection->addFieldToFilter('main_table.created_at', ['lt' => $beforeTime]);
        $tamaraOrderCollection->addFieldToFilter('main_table.is_authorised', 0);
        $tamaraOrderCollection->addFieldToSelect('order_id');
        $tamaraOrderCollection->getSelect()->join(['so' => $salesOrderTable], "main_table.order_id = so.entity_id", [])
            ->where("so.state = 'new' AND so.status = '" . $this->config->getCheckoutOrderCreateStatus($storeId) . "'");

        $orderIds = [];
        foreach ($tamaraOrderCollection as $tamaraOrder) {
            $orderIds[] = $tamaraOrder->getOrderId();
        }
        $this->coreRegistry->register('cancel_abandoned_order', true);
        $totalOrderCancelled = 0;
        $orderManagement = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Sales\Api\OrderManagementInterface::class);
        foreach ($orderIds as $id) {
            try {
                $orderManagement->cancel($id);
                $this->helper->log(["Cancelled order " . $id]);
                $totalOrderCancelled++;
            } catch (\Exception $exception) {
                $this->helper->log(["Cannot cancel order " . $id . ", error: " . $exception->getMessage()]);
            }
        }
        $this->helper->log(['Total order cancelled: ' . $totalOrderCancelled]);
    }
}