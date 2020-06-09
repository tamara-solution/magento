define([
    'jquery',
    'uiComponent',
    'loader'
], function ($, Component) {
    'use strict';
    return Component.extend({
        tamaraSuccessLogo: window.successTamara.tamaraSuccessLogo,
        tamaraLoginLink: window.successTamara.tamaraLoginLink
    });
});