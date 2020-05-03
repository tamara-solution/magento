<?php
namespace Tamara\Checkout\Model\Adminhtml\Source;

class PaymentAction implements \Magento\Framework\Option\ArrayInterface
{
    private const
        ACTION_AUTHORIZE = 'authorize';
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::ACTION_AUTHORIZE,
                'label' => __('Authorize')
            ]
        ];
    }
}
