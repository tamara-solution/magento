<?php

declare(strict_types=1);

namespace Tamara\Checkout\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;
use Magento\Backend\App\Action\Context;
use Tamara\Exception\RequestDispatcherException;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;

class Payments extends Action
{
    const PAY_BY_INSTALMENTS = 'PAY_BY_INSTALMENTS';
    const PAY_BY_LATER = 'PAY_BY_LATER';

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var TamaraAdapterFactory
     */
    protected $tamaraAdapterFactory;

    /**
     * @var ResourceConfig
     */
    protected $resourceConfig;

    /**
     * GetPaymentTypes constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param TamaraAdapterFactory $tamaraAdapterFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        TamaraAdapterFactory $tamaraAdapterFactory,
        ResourceConfig $resourceConfig
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->resourceConfig = $resourceConfig;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     * @throws RequestDispatcherException
     */
    public function execute()
    {
        $adapter = $this->tamaraAdapterFactory->create();
        $result = $adapter->getPaymentTypes('SA');


        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        if (empty($result)) {
            $resultJson->setData(['message' => __('Can not get the payment types')]);
        }

        $resultJson->setData($result);
        return $resultJson;
    }
}