<?php

namespace Tamara\Checkout\Model\Config\Source;

class ApiEnvironment implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => \Tamara\Checkout\Api\Data\CheckoutInformationInterface::SANDBOX_API_ENVIRONMENT,
                'label' => __('Sandbox')
            ],
            [
                'value' => \Tamara\Checkout\Api\Data\CheckoutInformationInterface::PRODUCTION_API_ENVIRONMENT,
                'label' => __('Production')
            ],
        ];
    }
}