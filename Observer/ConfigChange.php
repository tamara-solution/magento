<?php

declare(strict_types=1);

namespace Tamara\Checkout\Observer;

use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\RequestInterface;
use Magento\Payment\Model\Method\Logger;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class ConfigChange extends AbstractObserver
{
    private $request;
    private $logger;
    private $adapter;
    private $config;
    private $resourceConfig;

    public function __construct(
        RequestInterface $request,
        Logger $logger,
        TamaraAdapterFactory $adapter,
        BaseConfig $config,
        ResourceConfig $resourceConfig
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->config = $config;
        $this->resourceConfig = $resourceConfig;
    }

    public function execute(Observer $observer)
    {
        $paymentParams = $this->request->getParam('groups');
        $webhookEnabled = $paymentParams['tamara_checkout']['groups']['api_configuration']['fields']['enable_webhook']['value']
                        ?? $paymentParams['tamara_checkout']['groups']['api_configuration']['fields']['enable_webhook']['inherit'];

        $webhookId = $this->config->getWebhookId();
        $adapter = $this->adapter->create();

        if ($webhookEnabled && empty($webhookId)) {
            $adapter->registerWebhook();
        }

        if (!$webhookEnabled && $webhookId) {
            $adapter->deleteWebhook($webhookId);
        }

        return $this;
    }
}