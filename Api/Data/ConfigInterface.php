<?php

declare(strict_types=1);

namespace Tamara\Checkout\Api\Data;

interface ConfigInterface
{
    /**
     * @return string
     */
    public function getApiUrl(): string;

    /**
     * @return string
     */
    public function getApiToken(): string;

    /**
     * @return string
     */
    public function getNotificationUrl(): string;
}
