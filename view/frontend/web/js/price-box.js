define([
    'jquery',
    'Magento_Catalog/js/price-utils',
    'underscore',
    'mage/template'
], function ($, utils, _, mageTemplate) {
    'use strict';

    return function (priceBox) {
        return $.widget('mage.priceBox', priceBox, {

            /**
             * Render price unit block.
             */
            reloadPrice: function reDrawPrices() {
                this._super();

                //re-render pdp widget
                if (window.TamaraProductWidget) {
                    $("#tamara-product-widget").attr("data-price", this.cache.displayPrices.finalPrice.amount);
                    $("#tamara-product-widget").empty();
                    window.TamaraProductWidget.render();
                }
            },
        });
    }
});