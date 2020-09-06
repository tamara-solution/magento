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
        fullScreenLoader
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tamara_Checkout/payment/tamara_pay_later',
            },
            tamaraImageSrc: window.populateTamara.tamaraLogoImageUrl,
            tamaraLink: window.populateTamara.tamaraAboutLink,
            redirectAfterPlaceOrder: true,

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
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.SUCCESS, this.success);
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.FAILED, this.failed);
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.CANCELED, this.cancel);

                return this;
            },

            success: function() {
                let orderId = jQuery('#order-id').val()
                window.location.replace(url.build('tamara/payment/' + orderId + '/success'));
            },

            failed: function() {
                let orderId = jQuery('#order-id').val()
                window.location.replace(url.build('tamara/payment/' + orderId + '/failure'));
            },

            cancel: function() {
                let orderId = jQuery('#order-id').val()
                window.location.replace(url.build('tamara/payment/' + orderId + '/cancel'));
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

                    if (this.handleIframeCheckout()) {
                        return true;
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

            handleIframeCheckout: function() {
                if (!window.checkoutConfig.payment.tamara_iframe_checkout) {
                    return false;
                }

                fullScreenLoader.startLoader();

                let selectedPaymentMethod = $('input[name="payment[method]"]:checked').val();

                $.ajax({
                    url: url.build('tamara/payment/iframeCheckout'),
                    type: 'POST',
                    data: {payment_method: selectedPaymentMethod},
                    success: function (response) {
                        fullScreenLoader.stopLoader(true);
                        if (response.success) {
                            jQuery('#order-id').val(response.orderId);
                            TamaraCheckoutFrame.checkout(response.redirectUrl);
                        } else {
                            jQuery('#error-iframe').removeClass('hidden-error-iframe');
                            window.location.replace(url.build('checkout/cart'));
                        }
                    },
                    fail: function(){
                        fullScreenLoader.stopLoader(true);
                    }
                });

                return true;
            }
        });
    }
);