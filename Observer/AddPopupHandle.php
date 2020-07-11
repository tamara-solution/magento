<?php
namespace Tamara\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tamara\Checkout\Block\Product\Popup as Popup;

class AddPopupHandle implements ObserverInterface
{
    protected $customerSession;

    protected $popup;
    public function __construct(
        Popup $popup
    )
    {
        $this->popup = $popup;
    }
    public function execute(Observer $observer)
    {
        $layout = $observer->getEvent()->getLayout();
        if ($this->popup->isShowPopup())
        {
            $layout->getUpdate()->addHandle('tamara_popup_info');
        }
    }
}