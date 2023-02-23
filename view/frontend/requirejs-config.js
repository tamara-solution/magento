var config = {
    config: {
        mixins: {
            'Magento_Catalog/js/price-box': {
                'Tamara_Checkout/js/price-box': true
            },
            'Magento_Checkout/js/model/quote': {
                'Tamara_Checkout/js/model/quote-mixin': true
            }
        }
    }
};
