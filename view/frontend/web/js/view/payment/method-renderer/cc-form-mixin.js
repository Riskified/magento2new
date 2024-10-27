define([
    'jquery',
    'Riskified_Decider/js/model/advice',
    'PayPal_Braintree/js/view/payment/3d-secure',
    'PayPal_Braintree/js/view/payment/validator-handler',
], function($, advice, verify3DSecure, validator){
    'use strict';

    var mixin = {
         handleNonce: function (data) {
            var self = this;

            let generalCallback = function () {
                self._super();
            };

            let denyCallback = function () {
                throw new Error();
            };

            let disabledCallback = function () {
                let config = window.checkoutConfig.payment;
                window.checkoutConfig.payment[verify3DSecure.getCode()].enabled = true;
                verify3DSecure.setConfig(config[verify3DSecure.getCode()]);
                self.validatorManager.add(verify3DSecure);
            };

            try {
                advice.additionalPayload.bin = data.details.bin;
                advice.additionalPayload.type = data.details.cardType;
                advice.additionalPayload.exp_month = data.details.expirationMonth;
                advice.additionalPayload.exp_year = data.details.expirationYear;
                advice.additionalPayload.last4 = data.details.lastFour;

                advice
                    .setGateway('braintree_cc')
                    .registerSuccessCallback(generalCallback)
                    .registerDenyCallback(denyCallback)
                    .registerDisabledCallback(disabledCallback)
                    .registerFailedCallback(disabledCallback)
                    .validate();

                if (!advice.valid){
                    return false;
                }
            } catch (e) {
                return false;
            }

             this._super(data);
         }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
