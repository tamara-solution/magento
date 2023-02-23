<?php

namespace Tamara\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Tamara\Checkout\Helper\AbstractData;

class CountryValidator extends AbstractValidator
{
    const CURRENCIES_COUNTRIES_ALLOWED = [
        'SAR' => 'SA',
        'AED' => 'AE',
        'KWD' => 'KW',
        'BHD' => 'BH',
        'QAR' => 'QA',
        'OMR' => 'OM'
    ];

    protected $tamaraHelper;

    public function __construct(
        ResultInterfaceFactory $resultFactory,
        AbstractData $tamaraHelper
    ) {
        $this->tamaraHelper = $tamaraHelper;
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject)
    {
        $storeCurrency = $this->tamaraHelper->getStoreCurrencyCode($validationSubject['storeId']);
        $isAllowedCurrency = $this->tamaraHelper->isAllowedCurrency($storeCurrency, $validationSubject['storeId']);
        if ($isAllowedCurrency) {
            if (!in_array($validationSubject['country'], explode(',',
                CountryValidator::CURRENCIES_COUNTRIES_ALLOWED[$storeCurrency]))
            ) {
                return $this->createResult(false);
            }
        } else {
            return $this->createResult(false);
        }

        return $this->createResult(true);
    }
}