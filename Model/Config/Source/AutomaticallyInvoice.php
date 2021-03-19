<?php

namespace Tamara\Checkout\Model\Config\Source;

class AutomaticallyInvoice implements \Magento\Framework\Option\ArrayInterface
{
    const GENERATE_AFTER_AUTHORISE = 1;
    const GENERATE_AFTER_CAPTURE = 2;

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