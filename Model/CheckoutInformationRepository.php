<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\UrlInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;

class CheckoutInformationRepository implements \Tamara\Checkout\Api\CheckoutInformationRepositoryInterface
{

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
    protected $tamaraHelper;
    private $tamaraOrderRepository;
    private $checkoutInformationFactory;
    protected $baseConfig;

    public function __construct(
        UrlInterface $urlBuilder,
        \Tamara\Checkout\Api\OrderRepositoryInterface $tamaraOrderRepository,
        \Tamara\Checkout\Model\CheckoutInformationFactory $checkoutInformationFactory,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        BaseConfig $baseConfig
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->checkoutInformationFactory = $checkoutInformationFactory;
        $this->tamaraHelper = $tamaraHelper;
        $this->baseConfig = $baseConfig;
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
        if ($this->baseConfig->useMagentoCheckoutSuccessPage()) {
            $successUrl = $this->urlBuilder->getUrl('checkout/onepage/success/');
        }
        $cancelUrl = $paymentController . 'cancel';
        $failureUrl = $paymentController . 'failure';

        /**
         * @var \Tamara\Checkout\Model\CheckoutInformation $checkoutInformation
         */
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