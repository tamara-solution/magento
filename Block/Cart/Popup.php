<?php

namespace Tamara\Checkout\Block\Cart;

use Magento\Framework\View\Element\Template;
use Tamara\Checkout\Gateway\Config\BaseConfig;
use Magento\Customer\Model\Session;
use Tamara\Checkout\Model\EmailWhiteListFactory;

class Popup extends \Tamara\Checkout\Block\Product\Popup
{
    protected $cart;

    public function __construct(
        Template\Context $context,
        \Magento\Framework\Registry $registry,
        BaseConfig $config,
        Session $customerSession,
        EmailWhiteListFactory $whiteListFactory,
        \Tamara\Checkout\Gateway\Config\InstalmentConfig $instalmentConfig,
        \Tamara\Checkout\Gateway\Config\PayLaterConfig $payLaterConfig,
        \Tamara\Checkout\Helper\AbstractData $tamaraHelper,
        \Magento\Checkout\Model\Cart $cart,
        array $data = []
    )
    {
        $this->cart = $cart;
        parent::__construct($context, $registry, $config, $customerSession, $whiteListFactory, $instalmentConfig, $payLaterConfig, $tamaraHelper, $data);
    }

    
    /**
     * @return float
     */
    public function getCurrentProductPrice() {

        return $this->cart->getQuote()->getGrandTotal();
    }

    public function getQuote() {
        return $this->cart->getQuote();
    }

    /**
     * @return bool
     */
    public function availableToShow() {
        $quote = $this->getQuote();
        if (!$this->config->getEnableTamaraPdpWidget($quote->getStoreId())) {
            return false;
        }
        $items = $quote->getAllVisibleItems();
        if (!empty($items)) {
            $excludeProductIds = explode("," , strval($this->config->getExcludeProductIds($quote->getStoreId())));
            foreach ($items as $item) {
                if (in_array($item->getProduct()->getId(), $excludeProductIds)) {
                    return false;
                }
            }
        }
        $whitelistConfig = $this->config->getIsUseWhitelist($this->tamaraHelper->getCurrentStore()->getId());
        if ($whitelistConfig) {
            if (!$this->isAllowWhitelistEmail()) {
                return false;
            }
        }
        return true;
    }

    public function getPageType()
    {
        return 'cart';
    }
}
