<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order;

class Capture extends \Tamara\Checkout\Helper\AbstractData
{
    protected $captureRepository;

    public function __construct(
        Context $context,
        \Magento\Framework\Locale\Resolver $locale,
        StoreManagerInterface $storeManager,
        \Tamara\Checkout\Model\CaptureRepository $captureRepository
    )  {
        $this->captureRepository = $captureRepository;
        parent::__construct($context, $locale, $storeManager);
    }

    /**
     * Check order that can capture (both partially or fully)
     * @param $order Order
     * @return bool
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function canCapture(Order $order): bool
    {
        $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $order->getId()]);
        if (!count($captures)) {
            return true;
        }
        $capturedAmount = 0;
        foreach ($captures as $row) {
            $capturedAmount += $row['total_amount'];
        }
        if (empty($order->getTotalPaid()) || $capturedAmount < $order->getTotalPaid()) {
            return true;
        }
        return false;
    }
}