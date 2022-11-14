define([
    'jquery',
    'Riskified_Decider/js/model/advice',
    'Magento_Braintree/js/view/payment/3d-secure',
    'Magento_Braintree/js/view/payment/validator-handler',
], function($, advice, verify3DSecure, validator){
    'use strict';

    var mixin = {
         beforePlaceOrder: function (data) {
            console.log(data.details);
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

                self._super();
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
            } catch (e) {
                return false;
            }
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});