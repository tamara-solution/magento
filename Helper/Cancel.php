<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Tamara\Checkout\Model\OrderRepository;

class Cancel extends \Tamara\Checkout\Helper\AbstractData
{

    /**
     * @var \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory
     */
    protected $tamaraAdapterFactory;
    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $magentoOrderRepository;
    /**
     * @var OrderRepository
     */
    private $tamaraOrderRepository;

    public function __construct(
        Context $context,
        \Magento\Framework\Locale\Resolver $locale,
        StoreManagerInterface $storeManager,
        \Tamara\Checkout\Gateway\Config\BaseConfig $tamaraConfig,
        \Magento\Sales\Model\OrderRepository $magentoOrderRepository,
        \Tamara\Checkout\Model\OrderRepository $tamaraOrderRepository,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory
    ) {
        $this->magentoOrderRepository = $magentoOrderRepository;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        parent::__construct($context, $locale, $storeManager, $tamaraConfig);
    }

    public function cancelOrder($orderId): void
    {

        $this->log(['Start to cancel order from command line, order id: ' . $orderId]);
        $order = $this->magentoOrderRepository->get($orderId);
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);

        $data['tamara_order_id'] = $tamaraOrder->getTamaraOrderId();
        $data['order_id'] = $order->getId();
        $data['total_amount'] = $order->getGrandTotal();
        $data['tax_amount'] = $order->getTaxAmount();
        $data['shipping_amount'] = $order->getShippingAmount();
        $data['discount_amount'] = $order->getDiscountAmount();
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['items'] = $order->getAllVisibleItems();

        $tamaraAdapter = $this->tamaraAdapterFactory->create();
        $tamaraAdapter->cancel($data);
    }

}