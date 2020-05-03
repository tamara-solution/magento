<?php

namespace Tamara\Checkout\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;

class AuthorizeResponse implements HandlerInterface
{

    public function handle(array $handlingSubject, array $response)
    {
       return ['Alright, successful'];
    }
}