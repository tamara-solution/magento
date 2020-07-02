<?php

namespace Tamara\Checkout\Model;

use Tamara\Checkout\Api\EmailWhiteListRepositoryInterface;
use Tamara\Checkout\Model\ResourceModel\EmailWhiteList as WhitelistResource;

class EmailWhiteListRepository implements EmailWhiteListRepositoryInterface
{
    /**
     * @var WhitelistResource
     */
    private $resourceModel;

    /**
     * WhitelistRepository constructor.
     * @param WhitelistResource $resourceModel
     */
    public function __construct(
        WhitelistResource $resourceModel
    ) {
        $this->resourceModel = $resourceModel;
    }


    public function isEmailWhitelisted($email): bool
    {
        return $this->resourceModel->getWhitelistedEmail($email);
    }
}