<?php

declare(strict_types=1);

namespace Tamara\Checkout\Model;

use Tamara\Checkout\Api\Data\ConfigInterface;

class Config implements ConfigInterface
{
    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $apiToken;

    /**
     * @var string
     */
    private $notificationUrl;

    /**
     * @var string
     */
    private $paymentLimits;

    public function __construct(
        string $apiUrl,
        string $apiToken,
        string $notificationUrl,
        string $paymentLimits
    ) {
        $this->apiUrl = $apiUrl;
        $this->apiToken = $apiToken;
        $this->notificationUrl = $notificationUrl;
        $this->paymentLimits = $paymentLimits;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function getNotificationUrl(): string
    {
        return $this->notificationUrl;
    }

    public function getPaymentLimits(): string
    {
        return $this->paymentLimits;
    }
}
