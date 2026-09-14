<?php

namespace Tamara\Checkout\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Helper\OrderReconciliation;

class OrderStatusSync
{
    /**
     * @var \Tamara\Checkout\Helper\AbstractData
     */
    protected $helper;

    /**
     * @var BaseConfig
     */
    protected $config;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \Tamara\Checkout\Model\ResourceModel\Order\CollectionFactory
     */
    private $tamaraOrderCollectionFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /** @var OrderReconciliation */
    private $orderReconciliation;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param TimezoneInterface $timezone
     * @param ResourceConnection $resourceConnection
     * @param \Tamara\Checkout\Helper\AbstractData $helper
     * @param \Tamara\Checkout\Model\ResourceModel\Order\CollectionFactory $tamaraOrderCollectionFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param OrderReconciliation $orderReconciliation
     * @param BaseConfig $config
     */
    public function __construct(
        TimezoneInterface                                            $timezone,
        ResourceConnection                                           $resourceConnection,
        \Tamara\Checkout\Helper\AbstractData                         $helper,
        \Tamara\Checkout\Model\ResourceModel\Order\CollectionFactory $tamaraOrderCollectionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface                  $orderRepository,
        OrderReconciliation                                          $orderReconciliation,
        BaseConfig                                                   $config
    )
    {
        $this->timezone = $timezone;
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
        $this->tamaraOrderCollectionFactory = $tamaraOrderCollectionFactory;
        $this->orderRepository = $orderRepository;
        $this->orderReconciliation = $orderReconciliation;
        $this->config = $config;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        if (!$this->config->isOrderStatusSyncEnabled()) {
            return;
        }

        $this->helper->log(["Run order status sync from cron"]);
        $beforeTime = $this->config->getOrderStatusSyncTime();
        $this->syncOrderStatus($beforeTime);
        $this->helper->log(["Done"]);
    }

    /**
     * Sync order status with Tamara API
     *
     * @param string $beforeTime Time interval string (e.g., "-40 minutes")
     * @param int|null $storeId Store ID
     * @return void
     */
    public function syncOrderStatus($beforeTime, $storeId = null)
    {
        // Use safe timezone conversion
        $beforeTimeUtc = $this->convertTimeToUtc($beforeTime, $storeId);

        $this->helper->log(["Processing orders created before: " . $beforeTimeUtc . " UTC"]);

        $tamaraOrderCollection = $this->tamaraOrderCollectionFactory->create();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $tamaraOrderCollection->addFieldToFilter('main_table.created_at', ['lt' => $beforeTimeUtc]);
        $tamaraOrderCollection->addFieldToFilter('main_table.canceled_from_console', ['eq' => false]);
        $tamaraOrderCollection->addFieldToSelect(['order_id', 'tamara_order_id']);
        $tamaraOrderCollection->getSelect()->join(['so' => $salesOrderTable], "main_table.order_id = so.entity_id", ['so.store_id'])
            ->where('so.state IN (?)', [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT]);
        if ($storeId) {
            $tamaraOrderCollection->getSelect()->where('so.store_id = ?', $storeId);
        }
        // Oldest-updated first so a transient lookup failure (which bumps updated_at)
        // yields the queue to other pending checkouts instead of blocking the limit(30).
        $tamaraOrderCollection->getSelect()->order('main_table.updated_at ASC');
        $tamaraOrderCollection->getSelect()->limit(30);

        $totalOrdersProcessed = 0;
        $totalOrdersAuthorized = 0;
        $totalOrdersCancelled = 0;

        foreach ($tamaraOrderCollection as $tamaraOrder) {
            try {
                $orderId = $tamaraOrder->getOrderId();
                $order = $this->orderRepository->get($orderId);
                $orderStatus = $this->orderReconciliation->reconcile($order, $tamaraOrder);
                if ($orderStatus === null) {
                    continue;
                }

                $this->helper->log(["Order " . $orderId . " has latest Tamara status: " . $orderStatus]);
                if (in_array($orderStatus, ['expired', 'declined', 'canceled', 'cancelled', 'refunded'], true)) {
                    $totalOrdersCancelled++;
                } elseif ((bool) $tamaraOrder->getIsAuthorised()) {
                    $totalOrdersAuthorized++;
                }

                $totalOrdersProcessed++;

            } catch (\Exception $exception) {
                $this->helper->log(["Error processing order " . $tamaraOrder->getOrderId() => $exception->getMessage()], true);
            }
        }

        $this->helper->log([
            'Total orders processed: ' . $totalOrdersProcessed,
            'Total orders authorized: ' . $totalOrdersAuthorized,
            'Total orders cancelled: ' . $totalOrdersCancelled
        ]);
    }

    /**
     * Convert time interval to UTC format for database comparison
     * This method ensures timezone consistency between server and database
     *
     * @param string $timeInterval Time interval string (e.g., "-40 minutes")
     * @param int|null $storeId Store ID for timezone context
     * @return string UTC formatted datetime string
     */
    private function convertTimeToUtc($timeInterval, $storeId = null)
    {
        try {
            // Get current time in the appropriate timezone
            // If storeId is provided, use store timezone, otherwise use default
            if ($storeId) {
                $currentDateTime = $this->timezone->date(null, $storeId);
            } else {
                $currentDateTime = $this->timezone->date();
            }

            // Apply the time interval (e.g., "-40 minutes")
            $targetDateTime = clone $currentDateTime;
            $targetDateTime->modify($timeInterval);

            // Convert to UTC for database comparison (Magento stores dates in UTC)
            $targetDateTime->setTimezone(new \DateTimeZone('UTC'));

            return $targetDateTime->format('Y-m-d H:i:s');

        } catch (\Exception $e) {
            // Fallback to the old method if timezone conversion fails
            $this->helper->log(["Warning: Timezone conversion failed, using fallback method" => $e->getMessage()], true);
            return gmdate('Y-m-d H:i:s', strtotime($timeInterval));
        }
    }

}
