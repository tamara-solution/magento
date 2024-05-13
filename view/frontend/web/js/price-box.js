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
                try {
                    this.updateTamaraWidget(this.cache.displayPrices.finalPrice.amount);
                } catch (error) {
                }
            },

            updateTamaraWidget: function updateTamaraWidget(price) {
                if (window.TamaraProductWidget) {
                    document.getElementsByClassName("tamara-product-widget")[0].setAttribute("data-price", price);
                    document.getElementsByClassName("tamara-product-widget")[0].innerHTML = '';
                    window.TamaraProductWidget.render();
                }
                if (window.TamaraWidgetV2) {
                    document.getElementsByTagName("tamara-widget")[0].setAttribute("amount", price);
                    window.TamaraWidgetV2.refresh();
                }
            }
        });
    }
});