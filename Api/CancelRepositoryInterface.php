<?php

namespace Tamara\Checkout\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Tamara\Checkout\Model\Cancel;

interface CancelRepositoryInterface
{
    /**
     * Save an cancel
     *
     * @param  Cancel $cancel
     * @throws CouldNotSaveException
     */
    public function save(Cancel $cancel);
}