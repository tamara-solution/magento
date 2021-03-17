var config = {
    "map": {
        "*": {
            tamaraCheckoutFrame: "https://cdn.tamara.co/checkout/checkoutFrame.min.js?v=1.1"
        }
    },
    config: {
        mixins: {
            'Magento_Catalog/js/price-box': {
                'Tamara_Checkout/js/price-box': true
            }
        }
    }
};
