<?php

namespace Tamara\Checkout\Api;

interface CheckoutInformationRepositoryInterface
{

    /**
     * @param int $magentoOrderId
     * @return \Tamara\Checkout\Api\Data\CheckoutInformationInterface
     */
    public function getTamaraCheckoutInformation($magentoOrderId);

}