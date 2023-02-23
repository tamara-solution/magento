/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Catalog/js/price-utils',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals'
    ],
    function (
        $,
        Component,
        priceUtils,
        placeOrderAction,
        additionalValidators,
        redirectOnSuccessAction,
        url,
        fullScreenLoader,
        quote,
        totals
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tamara_Checkout/payment/tamara_pay_now',
            },
            tamaraImageSrc: window.populateTamara.tamaraLogoImageUrl,
            tamaraBadgeSrc: window.populateTamara.tamaraBadgeUrl,
            tamaraLink: window.populateTamara.tamaraAboutLink,
            countryCode: window.populateTamara.tamaraCountryCode,
            currencyCode: window.checkoutConfig.totalsData.quote_currency_code,
            redirectAfterPlaceOrder: false,
            preventPlaceOrderWhenError: false,
            totals: quote.getTotals(),

            /**
             * After place order callback
             */
            afterPlaceOrder: function () {
                this.createTamaraOrder();
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'tamaraPayNow'
                    ]);

                return this;
            },

            successPayNow: function () {
                if (window.checkoutConfig.payment.tamara.use_magento_checkout_success) {
                    window.location.replace(url.build(window.checkoutConfig.defaultSuccessPageUrl));
                } else {
                    let orderId = window.magentoOrderId;
                    window.location.replace(url.build('tamara/payment/' + orderId + '/success'));
                }
            },

            failedPayNow: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/failure'));
            },

            cancelPayNow: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/cancel'));
            },

            getCode: function () {
                return 'tamara_pay_now';
            },

            getData: function () {
                return {
                    'method': this.item.method
                };
            },

            getMinLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara.payment_types.tamara_pay_now.min_limit);
            },

            getMinLimitAmount: function () {
                return window.checkoutConfig.payment.tamara.payment_types.tamara_pay_now.min_limit;
            },

            getMaxLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara.payment_types.tamara_pay_now.max_limit);
            },

            getMaxLimitAmount: function () {
                return window.checkoutConfig.payment.tamara.payment_types.tamara_pay_now.max_limit;
            },

            getGrandTotal: function () {
                let grandTotal = 0;
                if (this.totals()) {
                    grandTotal = totals.getSegment('grand_total').value;
                } else {
                    grandTotal = window.checkoutConfig.totalsData.grand_total;
                }
                return grandTotal;
            },

            isTotalAmountInLimit: function () {
                var tamaraConfig = window.checkoutConfig.payment.tamara.payment_types.tamara_pay_now;
                var grandTotal = this.getGrandTotal();
                return !(grandTotal < parseFloat(tamaraConfig.min_limit) || grandTotal > parseFloat(tamaraConfig.max_limit));
            },

            isPlaceOrderActive: function () {
                return true;
            },

            isArabicLanguage: function () {
                return (window.checkoutConfig.payment.tamara.locale_code).includes("ar_");
            },

            getPaymentLanguage: function () {
                if (this.isArabicLanguage()) {
                    return 'ar';
                }
                return 'en';
            },

            getPublicKey: function() {
                return window.checkoutConfig.payment.tamara.public_key;
            },

            /**
             * Place order.
             */
            placeOrder: function (data, event) {
                let self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function (response) {
                                window.magentoOrderId = response;
                                self.afterPlaceOrder();

                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            }
                        ).always(
                        function () {
                            self.isPlaceOrderActionAllowed(true);
                        }
                    );

                    return true;
                }

                return false;
            },

            /**
             * @return {*}
             */
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },

            createTamaraOrder: function () {
                jQuery('#error-iframe').addClass('hidden-error-iframe');
                fullScreenLoader.startLoader();
                $.ajax({
                    url: url.build('tamara/payment/placeOrder'),
                    type: 'POST',
                    data: {
                        'orderId' : window.magentoOrderId
                    },
                    success: function (response) {
                        fullScreenLoader.stopLoader(true);
                        if (response.success) {
                            jQuery('#order-id-pay-now').val(response.orderId);
                            window.magentoOrderId = response.orderId;
                            window.location.replace(response.redirectUrl);
                        } else {
                            jQuery('#error-iframe').removeClass('hidden-error-iframe').text(response.error);
                            setTimeout(() => jQuery('#error-iframe').addClass('hidden-error-iframe').text(''), 10000);

                            return false;
                        }
                    },
                    fail: function () {
                        fullScreenLoader.stopLoader(true);
                    }
                });
            },

            getWidgetVersion: function () {
                return window.checkoutConfig.payment.tamara.widget_version;
            },

            renderWidgetV2: function () {
                //render in quote-mixin.js
                return true;
            },

            renderWidget: function () {
                this.renderWidgetV2();
                return false;
            }
        });
    }
);
