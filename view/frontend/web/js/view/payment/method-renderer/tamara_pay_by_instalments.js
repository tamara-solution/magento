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
                template: 'Tamara_Checkout/payment/tamara_pay_by_instalments',
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
                        'tamaraPayByInstalments'
                    ]);

                TamaraCheckoutFrame.init();
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.SUCCESS, this.successPayByInstalments);
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.FAILED, this.failedPayByInstalments);
                TamaraCheckoutFrame.addEventHandlers(TamaraCheckoutFrame.Events.CANCELED, this.cancelPayByInstalments);

                return this;
            },

            successPayByInstalments: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/success'));
            },

            failedPayByInstalments: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/failure'));
            },

            cancelPayByInstalments: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/cancel'));
            },

            getCode: function () {
                return 'tamara_pay_by_instalments';
            },

            getData: function () {
                return {
                    'method': this.item.method
                };
            },

            getMinLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_by_instalments.min_limit);
            },

            getMinLimitAmount: function () {
                return window.checkoutConfig.payment.tamara_pay_by_instalments.min_limit;
            },

            getMaxLimit: function () {
                return priceUtils.formatPrice(window.checkoutConfig.payment.tamara_pay_by_instalments.max_limit);
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
                var tamaraConfig = window.checkoutConfig.payment.tamara_pay_by_instalments;
                var grandTotal = this.getGrandTotal();

                return !(grandTotal < parseFloat(tamaraConfig.min_limit) || grandTotal > parseFloat(tamaraConfig.max_limit));
            },

            shouldShowError: function () {
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

                jQuery('#error-iframe-pay-by-instalments').addClass('hidden-error-iframe');

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
                            jQuery('#order-id-pay-by-instalments').val(response.orderId);
                            window.magentoOrderId = response.orderId;
                            TamaraCheckoutFrame.checkout(response.redirectUrl);
                        } else {
                            jQuery('#error-iframe-pay-by-instalments').removeClass('hidden-error-iframe').text(response.error);
                            that.preventPlaceOrderWhenError = true;

                            setTimeout(() => jQuery('#error-iframe-pay-by-instalments').addClass('hidden-error-iframe').text(''), 10000);

                            return false;
                        }
                    },
                    fail: function () {
                        fullScreenLoader.stopLoader(true);
                    }
                });

                return true;
            },

            getNumberOfInstalments: function() {
              return  window.checkoutConfig.payment.tamara_pay_by_instalments.number_of_instalments;
            },

            getInstalmentPeriods: function () {
                let periods = [];
                let numberOfInstalments = this.getNumberOfInstalments();
                let grandTotal = this.getGrandTotal();
                let precision = 10000;
                let totalAmountAsInt = grandTotal * precision;
                let mod = totalAmountAsInt % (numberOfInstalments * precision);
                let instalmentAmount = (totalAmountAsInt - mod) / numberOfInstalments;
                let payForEachMonth = (instalmentAmount / precision).toFixed(2);
                let dueToDay = grandTotal - (payForEachMonth * (numberOfInstalments - 1));
                periods.push({'label': $.mage.__('Due today'), 'amount': dueToDay, 'formatted_amount': priceUtils.formatPrice(dueToDay)});
                for(let i = 1; i < numberOfInstalments; i++) {
                    let label = i;
                    if (i > 1) {
                        if (i == 2) {
                            label = $.mage.__("2 months later");
                        } else {
                            let labelTmpl = i + ' months later';
                            label = $.mage.__(labelTmpl);
                        }
                    } else {
                        label = $.mage.__('1 month later');
                    }
                    periods.push({'label': label, 'amount': payForEachMonth, 'formatted_amount': priceUtils.formatPrice(payForEachMonth)});
                }
                return periods;
            },

            getInstalmentPeriodsForArabic: function () {
                return this.getInstalmentPeriods().reverse();
            },

            getPeriodTitle: function () {
                return $.mage.__('3 interest-free installments');
            },

            isArabicLanguage: function () {
                return (window.checkoutConfig.payment.tamara_pay_by_instalments.locale_code).includes("ar_");
            },

            getPaymentLanguage: function () {
                if (this.isArabicLanguage()) {
                    return 'ar';
                }
                return 'en';
            }
        });
    }
);