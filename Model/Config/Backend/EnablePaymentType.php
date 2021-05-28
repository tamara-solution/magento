<?php


namespace Tamara\Checkout\Model\Config\Backend;

use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Helper\AbstractData;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class EnablePaymentType extends \Magento\Framework\App\Config\Value
{
    protected $tamaraConfig;
    protected $tamaraAdapterFactory;
    protected $tamaraHelper;
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        TamaraAdapterFactory $tamaraAdapterFactory,
        BaseConfig $tamaraConfig,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->tamaraConfig = $tamaraConfig;
        $this->tamaraHelper = $tamaraHelper;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return \Magento\Framework\App\Config\Value
     * @throws \Exception
     */
    public function beforeSave()
    {
        if (!$this->isValueChanged()) {
            return parent::beforeSave();
        }
        if (!empty($this->getValue())) {
            $adapter = $this->tamaraAdapterFactory->create();
            $storeCountryCode = $this->tamaraHelper->getStoreCountryCode();
            $response = $adapter->getClient()->getPaymentTypes($storeCountryCode);
            if (!$response->isSuccess()) {
                throw new \Exception("Tamara checkout config, error when get payment types, error message: " . __($response->getMessage()));
            }
            $paymentTypes = $adapter->parsePaymentTypesResponse($response);
            if ($this->getPath() == "payment/tamara_pay_later/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_LATER])) {
                    throw new \Exception("Pay later is not allowed for this merchant, please contact Tamara support");
                }
            }
            if ($this->getPath() == "payment/tamara_pay_by_instalments/active") {
                if (!isset($paymentTypes[\Tamara\Checkout\Controller\Adminhtml\System\Payments::PAY_BY_INSTALMENTS])) {
                    throw new \Exception("Pay by installments is not allowed for this merchant, please contact Tamara support");
                }
            }
        }
        return parent::beforeSave();
    }
}