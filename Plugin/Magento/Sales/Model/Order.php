<?php

namespace Tamara\Checkout\Plugin\Magento\Sales\Model;

class Order
{
    protected $tamaraHelper;
    private $tamaraOrderRepository;

    public function __construct(
        \Tamara\Checkout\Helper\AbstractData          $tamaraHelper,
        \Tamara\Checkout\Api\OrderRepositoryInterface $tamaraOrderRepository
    )
    {
        $this->tamaraHelper = $tamaraHelper;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
    }

    public function afterGetPayment(\Magento\Sales\Model\Order $subject, $result)
    {
        if ($result === null || $subject->getEntityId() === null) {
            return $result;
        }
        $resultMethod = $result->getMethod();
        if (\Tamara\Checkout\Model\Helper\PaymentHelper::isTamaraPayment($resultMethod)) {
            if ($this->tamaraHelper->isSingleCheckoutEnabled($subject->getStoreId())) {
                try {
                    $order = $this->tamaraOrderRepository->getTamaraOrderByOrderId($subject->getEntityId());
                } catch (\Exception $exception) {
                    return $result;
                }
                $paymentMethod = $order->getPaymentType();
                if (!empty($paymentMethod)) {
                    if ($resultMethod != $paymentMethod) {
                        $result->setMethod($paymentMethod);
                    }
                }
            }
        }
        return $result;
    }
}
