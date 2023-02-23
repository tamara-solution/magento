<?php

namespace Tamara\Checkout\Plugin\Block\Adminhtml\Order\View\Tab;

class Info
{
    private $orderRepository;
    protected $tamaraHelper;
    public function __construct(
        \Tamara\Checkout\Model\OrderRepository $orderRepository,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper
    )
    {
        $this->orderRepository = $orderRepository;
        $this->tamaraHelper = $tamaraHelper;
    }

    public function afterGetPaymentHtml(\Magento\Sales\Block\Adminhtml\Order\View\Tab\Info $subject, $result)
    {
        $result = str_replace("Split it up to 4 payments with Tamara, interest-free", "Tamara: split your payments. No hidden fees, no interest!", $result);
        $additionalInfo = "";
        $order = $subject->getOrder();
        if ($this->tamaraHelper->isSingleCheckoutEnabled($order->getStoreId())) {
            $tamaraOrder = $this->orderRepository->getTamaraOrderByOrderId($order->getId());
            $additionalInfo .= ("<br/>Payment type: " . $tamaraOrder->getPaymentType());
            if (\Tamara\Checkout\Gateway\Config\InstalmentConfig::isInstallmentsPayment($tamaraOrder->getPaymentType())) {
                $additionalInfo .= ("<br />Number of installments: " . $tamaraOrder->getNumberOfInstallments());
            }
            $additionalInfo .= "\n\n";
        }
        return ($result . $additionalInfo);
    }
}