define([

], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            tamaraLink: window.populateTamara.tamaraAboutLink,
            tamaraCartLogo: window.populateTamara.tamaraCartLogo,

            isItemsBlockExpanded: function () {
                return true;
            }
        });
    };
});
