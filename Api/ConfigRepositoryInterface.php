<?php

declare(strict_types=1);

namespace Tamara\Checkout\Api;

use Tamara\Checkout\Api\Data\ConfigInterface;

interface ConfigRepositoryInterface
{
    /**
     * Get tamara config
     *
     * @return \Tamara\Checkout\Api\Data\ConfigInterface
     */
    public function getConfig(): ConfigInterface;
}
