<?php

namespace Tamara\Checkout\Gateway\Request;


use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tamara\Model\Order\Address;

class AddressDataBuilder implements BuilderInterface
{
    private const
        SHIPPING_ADDRESS = 'shipping_address',
        BILLING_ADDRESS = 'billing_address';

    public function build(array $buildSubject): array
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $shippingAddress = $order->getShippingAddress() ?? $order->getBillingAddress();
        $billingAddress = $order->getBillingAddress();

        $shipping = new Address();
        $billing = new Address();

        if ($shippingAddress === null || $billingAddress === null) {
            throw new \InvalidArgumentException('The address should be provided');
        }

        $shipping->setFirstName($shippingAddress->getFirstname());
        $shipping->setLastName($shippingAddress->getLastname());
        $shipping->setLine1($shippingAddress->getStreetLine1());
        $shipping->setLine2($shippingAddress->getStreetLine2() ?? '');
        $shipping->setRegion($shippingAddress->getRegionCode());
        $shipping->setCity($shippingAddress->getCity());
        $shipping->setPhoneNumber($shippingAddress->getTelephone());
        $shipping->setCountryCode($shippingAddress->getCountryId());

        $billing->setFirstName($billingAddress->getFirstname());
        $billing->setLastName($billingAddress->getLastname());
        $billing->setLine1($billingAddress->getStreetLine1());
        $billing->setLine2($billingAddress->getStreetLine2() ?? '');
        $billing->setRegion($billingAddress->getRegionCode());
        $billing->setCity($billingAddress->getCity());
        $billing->setPhoneNumber($billingAddress->getTelephone());
        $billing->setCountryCode($billingAddress->getCountryId());


        return [
            self::BILLING_ADDRESS => $billing,
            self::SHIPPING_ADDRESS => $shipping
        ];
    }
}