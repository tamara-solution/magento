<?php

namespace Tamara\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class ResponseValidator extends AbstractValidator
{

    public function validate(array $validationSubject)
    {
        return true;
    }
}