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
                redirectAfterPlaceOrder: false
            },
            tamaraImageSrc: window.populateTamara.tamaraLogoImageUrl,
            tamaraLink: window.populateTamara.tamaraAboutLink,
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

            successPayByInstalments: function () {
                if (window.checkoutConfig.payment.tamara.use_magento_checkout_success) {
                    window.location.replace(url.build(window.checkoutConfig.defaultSuccessPageUrl));
                } else {
                    let orderId = window.magentoOrderId;
                    window.location.replace(url.build('tamara/payment/' + orderId + '/success'));
                }
            },

            failedPayByInstalments: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/failure'));
            },

            cancelPayByInstalments: function () {
                let orderId = window.magentoOrderId;
                window.location.replace(url.build('tamara/payment/' + orderId + '/cancel'));
            },

            getData: function () {
                return {
                    'method': this.item.method
                };
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

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function () {
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
                var errorElement = "#error-iframe-pay-by-instalments-" + this.getNumberOfInstalments();
                jQuery(errorElement).addClass('hidden-error-iframe');
                fullScreenLoader.startLoader();
                $.ajax({
                    url: url.build('tamara/payment/placeOrder'),
                    type: 'POST',
                    data: {},
                    success: function (response) {
                        fullScreenLoader.stopLoader(true);
                        if (response.success) {
                            jQuery('#order-id').val(response.orderId);
                            window.magentoOrderId = response.orderId;
                            window.location.replace(response.redirectUrl);
                        } else {
                            jQuery(errorElement).removeClass('hidden-error-iframe').text(response.error);
                            setTimeout(() => jQuery(errorElement).addClass('hidden-error-iframe').text(''), 10000);

                            return false;
                        }
                    },
                    fail: function () {
                        fullScreenLoader.stopLoader(true);
                    }
                });
            },

            getInstalmentPeriods: function () {
                let periods = [];
                let numberOfInstalments = this.getNumberOfInstalments();
                let grandTotal = this.getGrandTotal() * 100;
                let mod = grandTotal % numberOfInstalments;
                let payForEachMonth = (grandTotal - mod) / numberOfInstalments / 100;
                let dueToDay = parseFloat(((grandTotal - mod)/numberOfInstalments/100) + (mod/100)).toFixed(2);

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
                return (window.checkoutConfig.payment.tamara.locale_code).includes("ar_");
            },

            getPaymentLanguage: function () {
                if (this.isArabicLanguage()) {
                    return 'ar';
                }
                return 'en';
            },

            renderProductWidget: function () {
                var countExistTamaraProductWidget = 0;
                var existTamaraPaymentProductWidget = setInterval(function() {
                    if ($('.tamara-product-widget').length) {
                        if (window.TamaraProductWidget) {
                            window.TamaraProductWidget.render();
                            clearInterval(existTamaraPaymentProductWidget);
                        }
                    }
                    if (++countExistTamaraProductWidget > 33) {
                        clearInterval(existTamaraPaymentProductWidget);
                    }
                }, 300);
                return false;
            },

            renderInstallmentsPlanWidget: function (numberOfInstallments) {
                var countExistTamaraInstallmentsPlan = 0;
                var existTamaraInstallmentsPlan = setInterval(function() {
                    if ($('.tamara-installment-plan-widget').length) {
                        if (window.TamaraInstallmentPlan) {
                            $('.tamara-installment-plan-widget').empty();
                            window.TamaraInstallmentPlan.render();
                            clearInterval(existTamaraInstallmentsPlan);
                        }
                    }
                    if (++countExistTamaraInstallmentsPlan > 33) {
                        clearInterval(existTamaraInstallmentsPlan);
                    }
                }, 300);
                return false;
            }
        });
    }
);
