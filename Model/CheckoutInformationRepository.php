<?php

namespace Tamara\Checkout\Model;

class CheckoutInformationRepository implements \Tamara\Checkout\Api\CheckoutInformationRepositoryInterface
{

    protected $tamaraHelper;
    private $tamaraOrderRepository;
    private $checkoutInformationFactory;

    public function __construct(
        \Tamara\Checkout\Api\OrderRepositoryInterface $tamaraOrderRepository,
        \Tamara\Checkout\Model\CheckoutInformationFactory $checkoutInformationFactory,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper
    ) {
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->checkoutInformationFactory = $checkoutInformationFactory;
        $this->tamaraHelper = $tamaraHelper;
    }

    /**
     * @inheritDoc
     */
    public function getTamaraCheckoutInformation($magentoOrderId)
    {
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($magentoOrderId);
        $baseUrl = $this->tamaraHelper->getCurrentStore()->getBaseUrl();
        $paymentController = $baseUrl . 'tamara/payment/' . $magentoOrderId . '/';
        $successUrl = $paymentController . 'success';
        $cancelUrl = $paymentController . 'cancel';
        $failureUrl = $paymentController . 'failure';
        $checkoutInformation = $this->checkoutInformationFactory->create();
        $checkoutInformation->setMagentoOrderId($magentoOrderId);
        $checkoutInformation->setTamaraOrderId($tamaraOrder->getTamaraOrderId());
        $checkoutInformation->setPaymentSuccessRedirectUrl($successUrl);
        $checkoutInformation->setPaymentCancelRedirectUrl($cancelUrl);
        $checkoutInformation->setPaymentFailureRedirectUrl($failureUrl);
        $checkoutInformation->setRedirectUrl($tamaraOrder->getRedirectUrl());
        return $checkoutInformation;
    }
}