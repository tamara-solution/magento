<?php

namespace Tamara\Checkout\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScanOrder extends Command
{
    const START_TIME = 'start-time';
    const END_TIME = 'end-time';
    protected $helper;

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
     * @var \Tamara\Checkout\Model\ScanOrder
     */
    private $scanOrder;

    public function __construct(
        \Magento\Framework\App\State $state,
        \Tamara\Checkout\Helper\AbstractData $helper,
        \Tamara\Checkout\Model\ScanOrder $scanOrder,
        string $name = null
    ) {
        $this->state = $state;
        $this->helper = $helper;
        $this->scanOrder = $scanOrder;
        parent::__construct($name);
    }

    protected function process()
    {
        $this->helper->log(["Run scan orders from console"]);
        $this->scanOrder->setScanFromConsole(true);
        if ($this->input->getOption(self::END_TIME)) {
            $this->scanOrder->scan($this->input->getOption(self::START_TIME), $this->input->getOption(self::END_TIME));
        } else {
            $this->scanOrder->scan($this->input->getOption(self::START_TIME));
        }
    }

    protected function configure()
    {
        $this->setName("tamara:orders-scan");
        $this->setDescription("Update status of orders that pay with Tamara");
        $this->addOption(
            self::START_TIME,
            null,
            InputOption::VALUE_REQUIRED,
            'Start time to scan'
        );
        $this->addOption(
            self::END_TIME,
            null,
            InputOption::VALUE_OPTIONAL,
            'End time to scan'
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
}