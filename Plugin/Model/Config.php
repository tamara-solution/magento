<?php

namespace Tamara\Checkout\Plugin\Model;

class Config
{

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->registry = $registry;
    }

    public function beforeSave(\Magento\Config\Model\Config $subject)
    {
        $groups = $subject->getGroups();
        if (!empty($groups)) {
            $this->registry->register("tamara_config_groups", $groups, true);
        }
    }
}