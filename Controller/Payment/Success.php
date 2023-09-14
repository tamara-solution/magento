<?php

namespace Tamara\Checkout\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Tamara\Checkout\Api\OrderRepositoryInterface as TamaraOrderRepository;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Helper\CartHelper;

class Success extends Action
{
    protected $_pageFactory;
    protected $orderRepository;
    protected $config;
    protected $tamaraOrderRepository;
    /**
     * @var CartHelper;
     */
    private $cartHelper;
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory
     */
    private $tamaraAdapterFactory;

    protected $tamaraHelper;

    private $orderSender;

    private $tamaraInvoiceHelper;

    /**
     * @var \Tamara\Checkout\Helper\Transaction
     */
    protected $tamaraTransactionHelper;

    private $orderCommentSender;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        CartHelper $cartHelper,
        OrderRepositoryInterface $orderRepository,
        BaseConfig $config,
        Session $checkoutSession,
        TamaraOrderRepository $tamaraOrderRepository,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        OrderSender $orderSender,
        \Tamara\Checkout\Helper\Invoice $tamaraInvoiceHelper,
        \Tamara\Checkout\Helper\Transaction $tamaraTransactionHelper,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender
    ) {
        $this->_pageFactory = $pageFactory;
        parent::__construct($context);
        $this->cartHelper = $cartHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->tamaraOrderRepository = $tamaraOrderRepository;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->tamaraHelper = $tamaraHelper;
        $this->orderSender = $orderSender;
        $this->tamaraInvoiceHelper = $tamaraInvoiceHelper;
        $this->tamaraTransactionHelper = $tamaraTransactionHelper;
        $this->orderCommentSender = $orderCommentSender;
    }

    public function execute()
    {
        $logger = $this->_objectManager->get('TamaraCheckoutLogger');
        try {
            $orderId = $this->_request->getParam('order_id', 0);

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
            $storeId = $order->getStoreId();
            $tamaraOrder = $this->tamaraOrderRepository->getTamaraOrderByOrderId($orderId);
            $isAllowed = false;
            $magentoOrderState = $order->getState();
            if ($magentoOrderState == \Magento\Sales\Model\Order::STATE_NEW) {
                $isAllowed = true;
            }
            if ($magentoOrderState == \Magento\Sales\Model\Order::STATE_PROCESSING) {
                if ($tamaraOrder->getIsAuthorised()) {
                    $isAllowed = true;
                }
            }
            if (!$isAllowed) {
                return $this->redirectToCartPage();
            }
            $paymentMethod = null;
            if ($this->tamaraHelper->isSingleCheckoutEnabled($storeId)) {
                $adapter = $this->tamaraAdapterFactory->create($storeId);
                $remoteOrder = $adapter->getTamaraOrderFromRemote($order->getIncrementId());
                if ($remoteOrder->isSuccess()) {
                    $numberOfInstallments = null;
                    $paymentMethod = \Tamara\Checkout\Gateway\Config\BaseConfig::convertPaymentMethodFromTamaraToMagento($remoteOrder->getPaymentType());
                    if ($paymentMethod == \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAYMENT_TYPE_CODE) {
                        $numberOfInstallments = $remoteOrder->getInstalments();
                        if ($numberOfInstallments != 3) {
                            $paymentMethod = ($paymentMethod . "_" . $numberOfInstallments);
                        }
                    }
                    $tamaraOrder->setPaymentType($paymentMethod);
                    $tamaraOrder->setNumberOfInstallments($numberOfInstallments);
                    $this->tamaraOrderRepository->save($tamaraOrder);
                }
            }
        } catch (\Exception $exception) {
            return $this->redirectToCartPage();
        }
        try {
            if (!(bool) $tamaraOrder->getIsAuthorised()) {

                //authorize order
                $apiUrl = $this->config->getApiUrl($storeId);
                $apiToken = $this->config->getMerchantToken($storeId);
                $config = \Tamara\Configuration::create($apiUrl, $apiToken);
                $client = \Tamara\Client::create($config);
                $tamaraOrderId = $tamaraOrder->getTamaraOrderId();
                $response = $client->authoriseOrder(new \Tamara\Request\Order\AuthoriseOrderRequest($tamaraOrderId));

                if ($response->isSuccess()) {
                    $tamaraOrder->setIsAuthorised(1);
                    $this->tamaraOrderRepository->save($tamaraOrder);

                    $authoriseStatus = $this->config->getCheckoutAuthoriseStatus($storeId);
                    if (!empty($authoriseStatus)) {
                        $order->setState(Order::STATE_PROCESSING)->setStatus($authoriseStatus);
                    }

                    //set base amount paid
                    $grandTotal = $order->getGrandTotal();
                    $order->setTotalDue(0.00);
                    $order->setTotalPaid($grandTotal);
                    $order->getPayment()->setAmountPaid($grandTotal);
                    $order->getPayment()->setAmountAuthorized($grandTotal);
                    $baseAmountPaid = $order->getBaseGrandTotal();
                    $order->setBaseTotalDue(0.00);
                    $order->setBaseTotalPaid($baseAmountPaid);
                    $order->getPayment()->setBaseAmountPaid($baseAmountPaid);
                    $order->getPayment()->setBaseAmountAuthorized($baseAmountPaid);
                    $order->getPayment()->setBaseAmountPaidOnline($baseAmountPaid);
                    $this->orderSender->send($order);

                    $authorisedAmount = $order->getOrderCurrency()->formatTxt(
                        $order->getGrandTotal()
                    );

                    $authoriseComment = __('Tamara - order was authorised. The authorised amount is %1.', $authorisedAmount);
                    $this->tamaraInvoiceHelper->log(["Create transaction after authorise payment"]);
                    $this->tamaraTransactionHelper->saveAuthoriseTransaction($authoriseComment, $order, $order->getIncrementId());
                    if (in_array(\Tamara\Checkout\Model\Config\Source\EmailTo\Options::SEND_EMAIL_WHEN_AUTHORISE, $this->config->getSendEmailWhen($order->getStoreId()))) {
                        try {
                            $this->orderCommentSender->send($order, true, $authoriseComment);
                        } catch (\Exception $exception) {
                            $logger->debug(["Tamara - Error when sending authorise notification: " . $exception->getMessage()]);
                        }
                        $order->addCommentToStatusHistory(
                            __('Notified customer about order #%1 was authorised.', $order->getIncrementId()),
                            $this->config->getCheckoutAuthoriseStatus($order->getStoreId()),
                        false)->setIsCustomerNotified(true)->save();
                    }
                    $this->orderRepository->save($order);

                    if ($this->config->getAutoGenerateInvoice($order->getStoreId()) == \Tamara\Checkout\Model\Config\Source\AutomaticallyInvoice::GENERATE_AFTER_AUTHORISE) {
                        $this->tamaraInvoiceHelper->log(["Automatically generate invoice after authorise payment"]);
                        $this->tamaraInvoiceHelper->generateInvoice($order->getId());
                    }

                    //create capture transaction
                    $captureComment = __('Magento capture transaction created.');
                    $captureTransactionId = $order->getIncrementId() . "-" . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
                    $this->tamaraTransactionHelper->createTransaction($order, \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, $captureComment, $captureTransactionId);
                } else {
                    $successStatus = $this->config->getCheckoutSuccessStatus($storeId);
                    $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus($successStatus);
                    $order->addCommentToStatusHistory(__('Tamara - order checkout success, we will confirm soon'), false, false);
                    $order->getResource()->save($order);
                }
                if ($paymentMethod !== null && $order->getPayment()->getMethod() != $paymentMethod) {
                    $adapter = $this->tamaraAdapterFactory->create($storeId);
                    $adapter->updatePaymentMethodToDbDirectly($orderId, $paymentMethod);
                }
                //end
            }
        } catch (\Exception $e) {
            $logger->debug(['Tamara - Success has error' => $e->getMessage()]);
        }

        if (!empty($merchantSuccessUrl = $this->config->getMerchantSuccessUrl($storeId))) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($merchantSuccessUrl);
            return $resultRedirect;
        }

        if ($this->config->useMagentoCheckoutSuccessPage($storeId)) {
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success/');
        }

        //dispatch event onepage
        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$orderId],
                'order' => $order
            ]
        );

        $quoteId = $this->checkoutSession->getQuoteId();
        if ($quoteId) {
            $this->cartHelper->removeCartAfterSuccess($quoteId);
        }

        $page = $this->_pageFactory->create();
        $block = $page->getLayout()->getBlock('tamara_success');
        $block->setData('order_id', $orderId);
        $block->setData('order_increment_id', $order->getIncrementId());
        return $page;
    }

    public function redirectToCartPage() {
        $this->_redirect('checkout/cart');
        return $this->getResponse()->sendResponse();
    }
}