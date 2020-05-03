<?php

namespace Tamara\Checkout\Model\Helper;

use Exception;
use Magento\Sales\Api\Data\OrderInterface;

class CartHelper
{
    protected $quoteRepository;
    protected $checkoutSession;
    protected $eventManager;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    )
    {
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->eventManager = $eventManager;
    }

    /**
     * @param OrderInterface $order
     * @throws Exception
     */
    public function restoreCartFromOrder($order): void
    {
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $quote->setReservedOrderId(null);
        $quote->setIsActive(true);
        $quote->removePayment();
        $this->quoteRepository->save($quote);

        $this->checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
        $this->eventManager->dispatch('restore_quote', ['order' => $order, 'quote' => $quote]);
    }
}