<?php

namespace Tamara\Checkout\Model\Adapter;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Tamara\Checkout\Api\CancelRepositoryInterface;
use Tamara\Checkout\Api\CaptureRepositoryInterface;
use Tamara\Checkout\Api\OrderRepositoryInterface;
use Tamara\Checkout\Api\RefundRepositoryInterface;
use Tamara\Checkout\Gateway\Config\BaseConfig;

class TamaraAdapterFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var BaseConfig
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CaptureRepositoryInterface
     */
    private $captureRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $mageRepository;

    /**
     * @var RefundRepositoryInterface
     */
    private $refundRepository;

    /**
     * @var CancelRepositoryInterface
     */
    private $cancelRepository;

    /**
     * @var Config
     */
    private $resourceConfig;

    /**
     * @param ObjectManagerInterface                      $objectManager
     * @param BaseConfig                                  $config
     * @param Logger                                      $logger
     * @param OrderRepositoryInterface                    $orderRepository
     * @param CaptureRepositoryInterface                  $captureRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $mageRepository
     * @param RefundRepositoryInterface                   $refundRepository
     * @param CancelRepositoryInterface                   $cancelRepository
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        \Magento\Framework\Registry $registry,
        BaseConfig $config,
        Logger $logger,
        OrderRepositoryInterface $orderRepository,
        CaptureRepositoryInterface $captureRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $mageRepository,
        RefundRepositoryInterface $refundRepository,
        CancelRepositoryInterface $cancelRepository,
        Config $resourceConfig
    ) {
        $this->config = $config;
        $this->objectManager = $objectManager;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->captureRepository = $captureRepository;
        $this->mageRepository = $mageRepository;
        $this->refundRepository = $refundRepository;
        $this->cancelRepository = $cancelRepository;
        $this->resourceConfig = $resourceConfig;
    }

    /**
     * Creates instance of Tamara Adapter.
     *
     * @param int $storeId if null is provided as an argument, then current scope will be resolved
     * by \Magento\Framework\App\Config\ScopeCodeResolver (useful for most cases) but for adminhtml area the store
     * should be provided as the argument for correct config settings loading.
     * @return TamaraAdapter
     */
    public function create($storeId = null): TamaraAdapter
    {
        $groups = $this->registry->registry('tamara_config_groups');
        if (is_array($groups) && !empty($groups["tamara_checkout"]["groups"]["api_configuration"]["fields"])) {
            $notificationToken = $groups["tamara_checkout"]["groups"]["api_configuration"]["fields"]["notification_token"]["value"];
            $apiEnvironment = $groups["tamara_checkout"]["groups"]["api_configuration"]["fields"]["api_environment"]["value"];
            if ($apiEnvironment == \Tamara\Checkout\Api\Data\CheckoutInformationInterface::PRODUCTION_API_ENVIRONMENT) {
                $apiUrl = \Tamara\Checkout\Api\Data\CheckoutInformationInterface::PRODUCTION_API_URL;
            } else {
                $apiUrl = \Tamara\Checkout\Api\Data\CheckoutInformationInterface::SANDBOX_API_URL;
            }
            $merchantToken = $groups["tamara_checkout"]["groups"]["api_configuration"]["fields"]["merchant_token"]["value"];
        } else {
            $notificationToken = $this->config->getNotificationToken($storeId);
            $apiUrl = $this->config->getApiUrl($storeId);
            $merchantToken = $this->config->getMerchantToken($storeId);
        }
        return $this->objectManager->create(
            TamaraAdapter::class,
            [
                'apiUrl' => $apiUrl,
                'merchantToken' => $merchantToken,
                'notificationToken' => $notificationToken,
                'checkoutAuthoriseStatus' => $this->config->getCheckoutAuthoriseStatus(),
                'orderRepository' => $this->orderRepository,
                'captureRepository' => $this->captureRepository,
                'mageRepository' => $this->mageRepository,
                'refundRepository' => $this->refundRepository,
                'cancelRepository' => $this->cancelRepository,
                'logger' => $this->logger,
                'orderSender' => $this->objectManager->create(OrderSender::class),
                'resourceConfig' => $this->resourceConfig,
            ]
        );
    }
}
