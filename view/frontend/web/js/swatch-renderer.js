define([
    'jquery'
], function ($) {
    'use strict';

    return function (widget) {
        $.widget('mage.SwatchRenderer', widget, {

            _UpdatePrice: function () {
                this._super();
                var $widget = this,
                    options = _.object(_.keys($widget.optionsMap), {}),
                    result;

                $widget.element.find('.' + $widget.options.classes.attributeClass + '[option-selected]').each(function () {
                    var attributeId = $(this).attr('attribute-id');

                    options[attributeId] = $(this).attr('option-selected');
                });

                result = $widget.options.jsonConfig.optionPrices[_.findKey($widget.options.jsonConfig.index, options)];
                let price;
                try {
                    price = result.finalPrice.amount;
                    price = Number.parseFloat(price);
                }  catch (error) {
                    return;
                }

                if (price) {
                    this._UpdateTamaraWidget(price);
                }
            },

            _UpdateTamaraWidget: function (price) {
                if (window.TamaraProductWidget) {
                    document.getElementsByClassName("tamara-product-widget")[0].setAttribute("data-price", price);
                    document.getElementsByClassName("tamara-product-widget")[0].innerHTML = '';
                    window.TamaraProductWidget.render();
                }
                if (window.TamaraWidgetV2) {
                    document.getElementsByTagName("tamara-widget")[0].setAttribute("amount", price);
                    window.TamaraWidgetV2.refresh();
                }
            }
        });

        return $.mage.SwatchRenderer;
    };
});