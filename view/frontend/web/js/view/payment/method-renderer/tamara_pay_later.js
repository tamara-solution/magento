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
        'Magento_Checkout/js/model/totals',
        'tamaraCheckoutFrame'
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
                template: 'Tamara_Checkout/payment/tamara_pay_later',
            },
            tamaraImageSrc: window.populateTamara.tamaraLogoImageUrl,
            tamaraLink: window.populateTamara.tamaraAboutLink,
            currencyCode: window.checkoutConfig.totalsData.quote_currency_code,
            redirectAfterPlaceOrder: true,
            preventPlaceOrderWhenError: false,
            totals: quote.getTotals(),

            /**
             * After place order callback
             */
            afterPlaceOrder: function () {
                // Override this function and put after place order logic here
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'tamaraPayLater'
                    ]);

                TamaraCheckoutFrame.init();
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.SUCCESS, this.successPayLater);
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.FAILED, this.failedPayLater);
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.CANCELED, this.cancelPayLater);

                return this;
            },

            successPayLater: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/success'));
            },

            failedPayLater: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/failure'));
            },

            cancelPayLater: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/cancel'));
            },

            getCode: function () {
                return 'tamara_pay_later';
            },

            getData: function () {
                return {
                    'method': this.item.method
                };
            },

            getMinLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_later.min_limit);
            },

            getMaxLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_later.max_limit);
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
                var tamaraConfig = window.checkoutConfig.payment.tamara_pay_later;
                var grandTotal = this.getGrandTotal();
                return !(grandTotal < parseFloat(tamaraConfig.min_limit) || grandTotal > parseFloat(tamaraConfig.max_limit));
            },

            shouldShowError: function () {
                return !this.isTotalAmountInLimit();
            },

            isPlaceOrderActive: function () {
                return !!this.isTotalAmountInLimit();
            },

            isArabicLanguage: function () {
                return (window.checkoutConfig.payment.tamara_pay_by_instalments.locale_code).includes("ar_");
            },

            getPaymentLanguage: function () {
                if (this.isArabicLanguage()) {
                    return 'ar';
                }
                return 'en';
            },

            /**
             * Place order.
             */
            placeOrder: function (data, event) {
                let self = this;

                if (event) {
                    event.preventDefault();
                }

                jQuery('#error-iframe').addClass('hidden-error-iframe');

                if (this.validate() && additionalValidators.validate()) {
                    this.preventPlaceOrderWhenError = false;

                    if (this.handleIframeCheckout()) {
                        return true;
                    }

                    if (this.preventPlaceOrderWhenError) {
                        return false;
                    }

                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                        function () {
                            self.afterPlaceOrder();

                            if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
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

            handleIframeCheckout: function () {
                if (!window.checkoutConfig.payment.tamara_iframe_checkout) {
                    return false;
                }

                fullScreenLoader.startLoader();

                let selectedPaymentMethod = $('input[name="payment[method]"]:checked').val();
                let that = this;

                $.ajax({
                    url: url.build('tamara/payment/iframeCheckout'),
                    type: 'POST',
                    data: {payment_method: selectedPaymentMethod},
                    success: function (response) {
                        fullScreenLoader.stopLoader(true);
                        if (response.success) {
                            jQuery('#order-id').val(response.orderId);
                            window.magentoOrderId = response.orderId;
                            TamaraCheckoutFrame.checkout(response.redirectUrl);
                        } else {
                            jQuery('#error-iframe').removeClass('hidden-error-iframe').text(response.error);
                            that.preventPlaceOrderWhenError = true;

                            setTimeout(() => jQuery('#error-iframe').addClass('hidden-error-iframe').text(''), 10000);

                            return false;
                        }
                    },
                    fail: function () {
                        fullScreenLoader.stopLoader(true);
                    }
                });

                return true;
            }
        });
    }
);
