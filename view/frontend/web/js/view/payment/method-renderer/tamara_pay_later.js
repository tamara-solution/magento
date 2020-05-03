/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Catalog/js/price-utils'
    ],
    function (Component, priceUtils) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tamara_Checkout/payment/tamara_pay_later',
            },
            tamaraImageSrc: window.populateTamara.tamaraLogoImageUrl,
            tamaraLink: window.populateTamara.tamaraAboutLink,

            initObservable: function () {

                this._super()
                    .observe([
                        'tamaraPayLater'
                    ]);
                return this;
            },

            getCode: function() {
                return 'tamara_pay_later';
            },

            getData: function() {
                return {
                    'method': this.item.method
                };
            },

            getMinLimit: function() {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_later.min_limit);
            },

            getMaxLimit: function() {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_later.max_limit);
            },

            isTotalAmountInLimit: function() {
                var tamaraConfig = window.checkoutConfig.payment.tamara_pay_later;
                var grandTotal = window.checkoutConfig.totalsData.grand_total;

                return !(grandTotal < parseFloat(tamaraConfig.min_limit) || grandTotal > parseFloat(tamaraConfig.max_limit));
            },

            shouldShowError: function() {
                return !this.isTotalAmountInLimit();
            },

            isPlaceOrderActive: function () {
                return !!this.isTotalAmountInLimit();
            }
        });
    }
);