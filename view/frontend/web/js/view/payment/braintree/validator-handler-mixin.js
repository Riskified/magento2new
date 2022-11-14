define([
    'jquery',
    'mage/utils/wrapper',
    'mage/storage',
    'Magento_Braintree/js/view/payment/3d-secure',
    'Magento_Checkout/js/model/quote',
    'Riskified_Decider/js/model/advice',
], function ($, wrapper, storage, verify3DSecure, quote, advice) {
    'use strict';


    function getPaymentMethod()
    {
        let choosenPaymentMethod = $(".payment-method-title").find('input[type="radio"]:checked');
        return choosenPaymentMethod.attr('id');
    }

    return function (braintreeValidatorHandler) {
        braintreeValidatorHandler.validate = function(context, callback) {
            var self = this,
                config = this.getConfig(),
                deferred;

            // no available validators
            if (!self.validators.length) {
                let generalCallback = function () {
                    callback();
                };

                let denyCallback = function () {
                    throw new Error();
                };

                let disabledCallback = function () {
                    verify3DSecure.setConfig(config[verify3DSecure.getCode()]);
                    self.add(verify3DSecure);
                };

                try {
                    advice
                        .setGateway('braintree_cc')
                        .registerSuccessCallback(generalCallback)
                        .registerDenyCallback(denyCallback)
                        .registerDisabledCallback(disabledCallback)
                        .registerFailedCallback(disabledCallback)
                        .validate();
                } catch (e) {
                    return false;
                }
            }

            // get list of deferred validators
            deferred = $.map(self.validators, function (current) {
                return current.validate(context);
            });

            $.when.apply($, deferred)
                .done(function () {
                    callback();
                }).fail(function (error) {
                self.showError(error);
            });
        };

        return braintreeValidatorHandler;
    };
});
