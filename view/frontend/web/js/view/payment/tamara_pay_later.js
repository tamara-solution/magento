define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tamara_pay_later',
                component: 'Tamara_Checkout/js/view/payment/method-renderer/tamara_pay_later'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
