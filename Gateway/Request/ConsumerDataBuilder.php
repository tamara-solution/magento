<?php

namespace Tamara\Checkout\Gateway\Request;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\Method\Logger;
use Tamara\Model\Order\Consumer;

class ConsumerDataBuilder implements BuilderInterface
{
    private const
        CONSUMER = 'consumer';

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * ConsumerDataBuilder constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param Logger $logger
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        Logger $logger
    )
    {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->logger = $logger;
    }

    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();

        $consumer = new Consumer();

        try {
            $customer = $this->customerRepository->getById($order->getCustomerId());
            $address = $customer->getDefaultShipping() ?? $customer->getDefaultBilling();

            if (empty($address)) {
                $telephone = $order->getShippingAddress()->getTelephone();
            } else {
                $addressData = $this->addressRepository->getById($address);
                $telephone = $addressData->getTelephone();
            }

            $consumer->setFirstName($customer->getFirstname());
            $consumer->setLastName($customer->getLastname());
            $consumer->setEmail($customer->getEmail());
            $consumer->setPhoneNumber($telephone);
            $consumer->setIsFirstOrder($this->isFirstOrder($customer->getId()));
        } catch (\Exception $e) {
            $this->logger->debug([$e->getMessage()]);
            return [self::CONSUMER => ''];
        }

        return [self::CONSUMER => $consumer];
    }

    private function isFirstOrder($customerId): bool
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')
            ->getCollection()
            ->addAttributeToFilter('customer_id', $customerId)->getFirstItem();

        return empty($order);
    }
}
