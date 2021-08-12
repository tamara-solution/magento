<?php

namespace Tamara\Checkout\Model;

use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
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
    protected $storeManager;
    protected $orderRepository;

    public function __construct(
        UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
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
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritDoc
     */
    public function getTamaraCheckoutInformation($magentoOrderId)
    {
        $magentoOrder = $this->orderRepository->get($magentoOrderId);
        $storeId = $magentoOrder->getStoreId();
        $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($magentoOrderId);
        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
        $paymentController = $baseUrl . 'tamara/payment/' . $magentoOrderId . '/';
        $successUrl = $paymentController . 'success';
        $cancelUrl = $paymentController . 'cancel';
        $failureUrl = $paymentController . 'failure';
        if ($this->baseConfig->useMagentoCheckoutSuccessPage($storeId)) {
            $successUrl = $this->urlBuilder->getUrl('checkout/onepage/success/');
        } else {
            if (!empty($this->baseConfig->getMerchantSuccessUrl($storeId))) {
                $successUrl = $this->baseConfig->getMerchantSuccessUrl($storeId);
            }
        }
        if (!empty($this->baseConfig->getMerchantCancelUrl($storeId))) {
            $cancelUrl = $this->baseConfig->getMerchantCancelUrl($storeId);
        }
        if (!empty($this->baseConfig->getMerchantFailureUrl($storeId))) {
            $failureUrl = $this->baseConfig->getMerchantFailureUrl($storeId);
        }

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