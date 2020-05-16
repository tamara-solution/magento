<?php

namespace Tamara\Checkout\Gateway\Request;

use Magento\Framework\App\ProductMetadata;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Tamara\Checkout\Model\Helper\LocaleHelper;
use Tamara\Model\Money;
use Tamara\Model\Order\Discount;

class CommonDataBuilder implements BuilderInterface
{
    private const
        ORDER_ID = 'order_id',
        LOCALE = 'locale',
        CURRENCY = 'currency',
        TOTAL_AMOUNT = 'total_amount',
        COUNTRY_CODE = 'country_code',
        PAYMENT_TYPE = 'payment_type',
        PLATFORM = 'platform',
        DESCRIPTION = 'description',
        TAX_AMOUNT = 'tax_amount',
        DISCOUNT_AMOUNT = 'discount_amount',
        SHIPPING_AMOUNT = 'shipping_amount';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ProductMetadata
     */
    private $productMetaData;

    /**
     * CommonDataBuilder constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param ProductMetadata $productMetaData
     */
    public function __construct(OrderRepositoryInterface $orderRepository, ProductMetadata $productMetaData)
    {
        $this->orderRepository = $orderRepository;
        $this->productMetaData = $productMetaData;
    }


    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['order'])
            || !$buildSubject['order'] instanceof OrderInterface
        ) {
            throw new \InvalidArgumentException('Order data object should be provided');
        }

        /** @var OrderInterface $order */
        $order = $buildSubject['order'];
        $currencyCode = $buildSubject['order_currency_code'];

        $discountName = $order->getCouponCode() ?? 'N/A';
        $discountAmount = new Discount($discountName, new Money($order->getDiscountAmount(), $currencyCode));

        return [
            self::ORDER_ID => $order->getEntityId(),
            self::LOCALE => LocaleHelper::getLocale(),
            self::CURRENCY => $currencyCode,
            self::TOTAL_AMOUNT => new Money($order->getGrandTotal(), $currencyCode),
            self::TAX_AMOUNT => new Money($order->getTaxAmount(), $currencyCode),
            self::SHIPPING_AMOUNT => new Money($order->getShippingAmount(), $currencyCode),
            self::DISCOUNT_AMOUNT => $discountAmount,
            self::COUNTRY_CODE => $order->getBillingAddress()->getCountryId(),
            self::PAYMENT_TYPE => 'PAY_BY_LATER',
            self::PLATFORM => 'Magento Version: ' . $this->productMetaData->getVersion(),
            self::DESCRIPTION => 'Description'
        ];
    }
}