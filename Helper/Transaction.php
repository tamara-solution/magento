<?php

namespace Tamara\Checkout\Helper;

use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;

class Transaction extends \Tamara\Checkout\Helper\AbstractData
{
    /**
     * @param $message
     * @param $order \Magento\Sales\Model\Order
     * @param $captureId
     * @return string|null
     * @throws \Exception
     */
    public function saveCaptureTransaction($message, $order, $captureId)
    {
        return $this->createTransaction($order, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, $message,
            $captureId);
    }

    /**
     * @param $order \Magento\Sales\Model\Order
     * @param $type
     * @param $message
     * @param $transactionId
     * @return string|null
     * @throws \Exception
     */
    public function createTransaction($order, $type, $message, $transactionId = null)
    {
        try {
            $payment = $order->getPayment();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            /**
             * @var TransactionBuilder $transactionBuilder
             */
            $transactionBuilder = $objectManager->create(TransactionBuilder::class);

            /**
             * @var $transactionManager \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface
             */
            $transactionManager = $objectManager->create(\Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface::class);
            if (!$transactionId) {
                $transactionId = $transactionManager->generateTransactionId($payment, $type);
            }
            $transaction = $transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->build($type);

            $transactionRepository = $objectManager->create(TransactionRepositoryInterface::class);
            $transactionRepository->save($transaction);
            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setTransactionId($transactionId);
            $payment->setParentTransactionId($transactionId);
            $payment->setLastTransId($transactionId);
            $payment->setIsTransactionClosed(true);
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->log([$e->getMessage()]);
        }
        return null;
    }

    /**
     * @param $message
     * @param $order \Magento\Sales\Model\Order
     * @param $tamaraOrderId
     * @return string|null
     * @throws \Exception
     */
    public function saveAuthoriseTransaction($message, $order, $tamaraOrderId)
    {
        $transactionId = $tamaraOrderId . "-" . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH;
        return $this->createTransaction($order, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, $message,
            $transactionId);
    }
}