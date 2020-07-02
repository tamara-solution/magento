<?php

declare(strict_types=1);


namespace Tamara\Checkout\Model;

use Magento\Framework\Model\AbstractModel;

class EmailWhiteList extends AbstractModel
{
    const CACHE_TAG = 'tamara_whitelist';

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    protected function _construct()
    {
        $this->_init(\Tamara\Checkout\Model\ResourceModel\EmailWhiteList::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerEmail()
    {
        return $this->_getData('customer_email');
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerEmail($customerEmail)
    {
        $this->setData('customer_email', $customerEmail);
        return $this;
    }

}