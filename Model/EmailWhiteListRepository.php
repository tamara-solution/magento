<?php

namespace Tamara\Checkout\Model;

use Tamara\Checkout\Api\EmailWhiteListRepositoryInterface;
use Tamara\Checkout\Model\ResouceModel\EmailWhiteList as WhitelistResouce;

class EmailWhiteListRepository implements EmailWhiteListRepositoryInterface
{
    /**
     * @var WhitelistResouce
     */
    private $resourceModel;

    /**
     * WhitelistRepository constructor.
     * @param WhitelistResouce $resourceModel
     */
    public function __construct(
        WhitelistResouce $resourceModel
    ) {
        $this->resourceModel = $resourceModel;
    }


    public function isEmailWhitelisted($email): bool
    {
        return $this->resourceModel->getWhitelistedEmail($email);
    }
}