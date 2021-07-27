/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Tamara_Checkout/js/view/payment/method-renderer/tamara_pay_by_instalments_abstract',
        'Magento_Catalog/js/price-utils'
    ],
    function (
        Component,
        priceUtils
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tamara_Checkout/payment/tamara_pay_by_instalments_9',
                redirectAfterPlaceOrder: false
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'tamaraPayByInstalments9'
                    ]);

                return this;
            },

            getMinLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_by_instalments_9.min_limit);
            },

            getMinLimitAmount: function () {
                return window.checkoutConfig.payment.tamara_pay_by_instalments_9.min_limit;
            },

            getMaxLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_by_instalments_9.max_limit);
            },

            getMaxLimitAmount: function () {
                return window.checkoutConfig.payment.tamara_pay_by_instalments_9.max_limit;
            },

            isTotalAmountInLimit: function () {
                var tamaraConfig = window.checkoutConfig.payment.tamara_pay_by_instalments_9;
                var grandTotal = this.getGrandTotal();

                return !(grandTotal < parseFloat(tamaraConfig.min_limit) || grandTotal > parseFloat(tamaraConfig.max_limit));
            },

            getNumberOfInstalments: function() {
                return  9;
            }
        });
    }
);
