<?php

namespace Tamara\Checkout\Gateway\Http\Client;

use Tamara\Exception\RequestDispatcherException;

class AuthorizeClient extends AbstractClient
{
    /**
     * @param array $data
     * @return array
     * @throws RequestDispatcherException
     */
    protected function process(array $data)
    {
        $storeId = $data['store_id'] ?? null;
        return  $this->adapterFactory->create($storeId)->createCheckout($data);
    }

}
