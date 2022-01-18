<?php

namespace Tamara\Checkout\Model\Config\Source\TriggerEvents;

class Options implements \Magento\Framework\Option\ArrayInterface
{
    const CAPTURE_ORDER = 1;
    const CANCEL_ORDER = 2;
    const REFUND_ORDER = 3;

    public function toOptionArray()
    {
        return [
            ['value' => self::CAPTURE_ORDER, 'label' => __('Capture order')],
            ['value' => self::CANCEL_ORDER, 'label' => __('Cancel order')],
            ['value' => self::REFUND_ORDER, 'label' => __('Refund order (Credit memo)')]
        ];
    }
}