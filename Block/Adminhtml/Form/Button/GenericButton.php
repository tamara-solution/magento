<?php

namespace Tamara\Checkout\Block\Adminhtml\Form\Button;

use Magento\Backend\Block\Widget\Context;

class GenericButton extends \Magento\Backend\Block\Widget\Container
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    )
    {

        $this->context = $context;
    }

    /**getEntityId
     * Return Entity ID
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->context->getRequest()->getParam('id');
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
