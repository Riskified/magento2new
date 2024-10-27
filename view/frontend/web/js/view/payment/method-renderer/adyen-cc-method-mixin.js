define(
    [
        'jquery',
        'ko',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/installments',
        'mage/url',
        'Magento_Vault/js/view/payment/vault-enabler',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_Payment/js/model/adyen-checkout',
        'Riskified_Decider/js/model/advice'

    ],
    function ($,
              ko,
              Component,
              customer,
              additionalValidators,
              quote,
              installmentsHelper,
              url,
              VaultEnabler,
              urlBuilder,
              fullScreenLoader,
              errorProcessor,
              adyenPaymentService,
              adyenConfiguration,
              AdyenPaymentModal,
              adyenCheckout,
              advice
  ) {

        'use strict';

        var mixin = {

            /**
             * Based on the response we can start a 3DS2 validation or place the order
             * Extended by Riskified with 3D Secure enabled after Riskified-Advise-Api-Call.
             * @param responseJSON
             */

            validate: function() {
                let state = $.Deferred();
                let form = 'form[data-role=adyen-cc-form]';

                let validate = $(form).validation() &&
                    $(form).validation('isValid') &&
                    this.cardComponent.isValid;

                if (!validate) {
                    this.cardComponent.showValidation();
                    return false;
                }

                function disabledCallback() {}
                function successCallback() {}
                function denyCallback() {}

                try {
                    advice.additionalPayload.bin = this.creditCardNumber;
                    advice.additionalPayload.type = this.creditCardType;
                    advice.additionalPayload.exp_month = this.creditCardExpMonth;
                    advice.additionalPayload.exp_year = this.creditCardExpYear;
                    advice.additionalPayload.last4 = this.creditCardNumber;

                    advice
                        .setGateway("adyen_cc")
                        .registerSuccessCallback(successCallback)
                        .registerDenyCallback(denyCallback)
                        .registerFailedCallback(denyCallback)
                        .registerDisabledCallback(disabledCallback)
                        .validate();

                    if (!advice.valid){
                        state.reject($t('Please try again with another form of payment.'));
                        return false;
                    }
                } catch(e) {
                    return false;
                }

                return true;
            },
        };

        return function (target) {
            return target.extend(mixin);
        };
    }
);
