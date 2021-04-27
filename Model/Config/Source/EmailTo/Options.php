<?php

namespace Tamara\Checkout\Model\Config\Source\EmailTo;

class Options implements \Magento\Framework\Option\ArrayInterface
{
    const SEND_EMAIL_WHEN_AUTHORISE = 1;
    const SEND_EMAIL_WHEN_CANCEL_ORDER = 2;
    const SEND_EMAIL_WHEN_REFUND_ORDER = 3;

    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => __('Disabled')],
            ['value' => self::SEND_EMAIL_WHEN_AUTHORISE, 'label' => __('Authorise order')],
            ['value' => self::SEND_EMAIL_WHEN_CANCEL_ORDER, 'label' => __('Cancel order')],
            ['value' => self::SEND_EMAIL_WHEN_REFUND_ORDER, 'label' => __('Refund order')]
        ];
    }
}