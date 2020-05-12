<?php

namespace Tamara\Checkout\Api;

interface EmailWhiteListRepositoryInterface
{
    /**
     * @param $email
     * @return bool
     */
    public function isEmailWhitelisted($email): bool;
}