var tamara_checkout_enabled = !window.tamara_checkout_disabled
var config = {
    config: {
        mixins: {
            'Magento_Catalog/js/price-box': {
                'Tamara_Checkout/js/price-box': tamara_checkout_enabled
            },
            'Magento_Checkout/js/model/quote': {
                'Tamara_Checkout/js/model/quote-mixin': tamara_checkout_enabled
            }
        }
    }
};
