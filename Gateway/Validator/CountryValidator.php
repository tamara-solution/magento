<?php

namespace Tamara\Checkout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class CountryValidator extends AbstractValidator
{

    const COUNTRIES_CURRENCIES_ALLOWED = [
        'SA' => 'SAR',
        'AE' => 'AED',
        'KW' => 'KWD',
        'BH' => 'BHD',
        'QA' => 'QAR'
    ];

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
        $storeId = $validationSubject['storeId'];

        if (!in_array($validationSubject['country'], explode(',', \Tamara\Checkout\Model\Method\Checkout::ALLOWED_COUNTRIES))) {
            return $this->createResult(false);
        }

        $isValid = true;
        if ((int)$this->tamaraHelper->getTamaraConfig()->getValue('allowspecific', $storeId) === 1) {
            $availableCountries = explode(
                ',',
                $this->tamaraHelper->getTamaraConfig()->getValue('specificcountry', $storeId)
            );

            if (!in_array($validationSubject['country'], $availableCountries)) {
                $isValid =  false;
            }
        }
        if ($isValid) {

            //validate currency
            $storeCurrencyCode = $this->tamaraHelper->getStoreCurrencyCode();
            if (!in_array($storeCurrencyCode, explode(',', \Tamara\Checkout\Model\Method\Checkout::ALLOWED_CURRENCIES))) {
                $isValid = false;
            } else {

                //doesnt support cross currencies
                if ($storeCurrencyCode != self::COUNTRIES_CURRENCIES_ALLOWED[$validationSubject['country']]) {
                    $isValid = false;
                }
            }
        }

        return $this->createResult($isValid);
    }
}