<?php

namespace Tamara\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class CountryValidator extends AbstractValidator
{

    protected $tamaraHelper;

    public function __construct(ResultInterfaceFactory $resultFactory,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper
    )
    {
        $this->tamaraHelper = $tamaraHelper;
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject)
    {
        if ($this->tamaraHelper->getStoreCountryCode($validationSubject['storeId']) != $validationSubject['country']) {
            return $this->createResult(false, []);
        }
        return $this->createResult(true, []);
    }
}