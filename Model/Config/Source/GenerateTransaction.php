<?php

namespace Tamara\Checkout\Model\Config\Source;

class GenerateTransaction extends AutomaticallyInvoice
{
    const GENERATE_WHEN_CREATE_ORDER = 3;

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('Disabled')
            ],
            [
                'value' => self::GENERATE_WHEN_CREATE_ORDER,
                'label' => __('When create order')
            ],
            [
                'value' => self::GENERATE_AFTER_AUTHORISE,
                'label' => __('After authorise')
            ],
            [
                'value' => self::GENERATE_AFTER_CAPTURE,
                'label' => __('After capture')
            ],
        ];
    }
}