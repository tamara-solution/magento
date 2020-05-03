define([
    'jquery',
    'uiComponent',
    'loader'
], function ($, Component) {
    'use strict';

    $(document).ready(function(){
        $('#content-success').hide();
        let startTime = new Date().getTime();
        let myTimeOut;
        let orderId = $('#order_id');

        (function runCheck() {
            if(new Date().getTime() - startTime > 60000){
                clearMyTimeOut();
                return;
            }
            $.ajax({
                url: '/tamara/payment/check',
                type: 'post',
                data: {'order_id': orderId.val()},
                dataType: 'json',
                beforeSend: function() {
                    $('#success-area').trigger('processStart');
                },
                success: function(json) {
                    if (json['success']) {
                        $('#content-success').css('display', 'block');
                        clearMyTimeOut();
                    }
                    $('#success-area').trigger('processStop');
                }
            });


            myTimeOut = setTimeout(runCheck, 15000);
        })();

        function clearMyTimeOut() {
            clearTimeout(myTimeOut);
        }
    });
    return Component.extend({
        tamaraSuccessLogo: window.successTamara.tamaraSuccessLogo,
        tamaraLoginLink: window.successTamara.tamaraLoginLink
    });
});