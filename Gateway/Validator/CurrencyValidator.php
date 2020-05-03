<?php

namespace Tamara\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class CurrencyValidator extends AbstractValidator
{

    public function validate(array $validationSubject)
    {
        return $this->createResult(true, []);
    }
}