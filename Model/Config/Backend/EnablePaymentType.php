<?php


namespace Tamara\Checkout\Model\Config\Backend;

use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class EnablePaymentType extends \Magento\Framework\App\Config\Value
{
    protected $tamaraConfig;
    protected $tamaraAdapterFactory;
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        TamaraAdapterFactory $tamaraAdapterFactory,
        BaseConfig $tamaraConfig,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->tamaraConfig = $tamaraConfig;
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
            $response = $adapter->getClient()->getPaymentTypes('SA');
            if (!$response->isSuccess()) {
                throw new \Exception("Tamara checkout config, error when get payment types, error message: " . __($response->getMessage()));
            }
        }
        return parent::beforeSave();
    }
}