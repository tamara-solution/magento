<?php

namespace Tamara\Checkout\Model\Helper;

use Tamara\Model\Money;
use Tamara\Model\Order\Address;
use Tamara\Model\Order\Consumer;
use Tamara\Model\Order\MerchantUrl;
use Tamara\Model\Order\Order;

class OrderHelper
{
    public static function createTamaraOrderFromArray(array $data): Order
    {
        $order = new Order();

        $order->setOrderReferenceId($data['order_id']);
        $order->setLocale($data['locale']);
        $order->setCurrency($data['currency']);
        $order->setTotalAmount($data['total_amount']);
        $order->setTaxAmount($data['tax_amount']);
        $order->setShippingAmount($data['shipping_amount']);
        $order->setDiscount($data['discount_amount']);
        $order->setCountryCode($data['country_code']);
        $order->setPaymentType($data['payment_type']);
        $order->setPlatform($data['platform']);
        $order->setDescription($data['description']);
        $order->setShippingAddress($data['shipping_address']);
        $order->setBillingAddress($data['billing_address']);
        $order->setMerchantUrl($data['merchant_url']);
        $order->setConsumer($data['consumer']);
        $order->setItems($data['items']);

        return $order;
    }
}
