<?php

namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\IntegrationException;
use Magento\Sales\Model\Order\Invoice;
use Tamara\Checkout\Api\CaptureRepositoryInterface;

class InvoiceSaveAfter extends AbstractObserver
{
    private $captureRepository;

    /**
     * InvoiceSaveAfter constructor.
     * @param $captureRepository
     */
    public function __construct(
        CaptureRepositoryInterface $captureRepository
    ){
        $this->captureRepository = $captureRepository;
    }


    /**
     * @param Observer $observer
     * @throws IntegrationException
     */
    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $payment = $order->getPayment();

        if ($payment === null) {
            throw new IntegrationException(__('The order should have payment'));
        }

        $captures = $this->captureRepository->getCaptureByConditions(['order_id' => $order->getId()]);

        if (!empty($captures)) {
            return;
        }

        if ($this->isTamaraPayment($payment->getMethod())) {
            throw new IntegrationException(__('The tamara payment should be handle by ship not invoice'));
        }
    }
}