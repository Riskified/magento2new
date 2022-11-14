define(
    [
        'jquery',
        'ko',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/installments',
        'mage/url',
        'Magento_Vault/js/view/payment/vault-enabler',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Adyen_Payment/js/threeds2-js-utils',
        'Adyen_Payment/js/model/threeds2',
        'Magento_Checkout/js/model/error-processor',
        'Riskified_Decider/js/model/advice'

    ],
    function ($, ko, Component, customer, creditCardData, additionalValidators, quote, installmentsHelper, url, VaultEnabler, urlBuilder, storage, fullScreenLoader, setPaymentMethodAction, selectPaymentMethodAction, threeDS2Utils, threeds2, errorProcessor, advice) {

        'use strict';

        var mixin = {

            /**
             * Based on the response we can start a 3DS2 validation or place the order
             * Extended by Riskified with 3D Secure enabled after Riskified-Advise-Api-Call.
             * @param responseJSON
             */
            validateThreeDS2OrPlaceOrder: function (responseJSON) {
                var self = this;
                var response = JSON.parse(responseJSON),
                    threeDS2Status = response.threeDS2,
                    quoteThreeDSecureState = quote.getThreeDSecureStatus();

                if(quoteThreeDSecureState == 0){
                    function disabledCallback() {
                        self.basicThreeDValidators(response);
                        quote.setThreeDSecureStatus(quoteThreeDSecureState + 1);
                        self.renderThreeDS2Component(response.type, response.token);
                    }

                    function successCallback() {
                        window.location.replace(url.build(
                            window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl)
                        );
                    }

                    function denyCallback() {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(false);
                        alert.showError("The order was declined.");
                    }

                    try {
                        advice
                            .setGateway("adyen_cc")
                            .registerSuccessCallback(successCallback)
                            .registerDenyCallback(denyCallback)
                            .registerFailedCallback(denyCallback)
                            .registerDisabledCallback(disabledCallback)
                            .validate();
                    } catch(e) {
                        return false;
                    }
                }else{
                    self.basicThreeDValidators(response);
                }
            },
            /**
             * Build-in Adyen 3D Secure Validator logic. No Riskified Advise logic included.
             */
            basicThreeDValidators: function (response) {
                //old way of 3D Secure validation (without Riskified)
                if (!!response.threeDS2) {
                    // render component
                    this.renderThreeDS2Component(response.type, response.token);
                } else {
                    window.location.replace(url.build(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl)
                    );
                }
            },
            /**
             * Modiffied function for rendering the 3DS2.0 components.
             * In case 3DSecure refuse submit try then order data is send to Riskified.
             * @param type
             * @param token
             */
            renderThreeDS2Component: function (type, token) {
                var self = this;
                var threeDS2Node = document.getElementById('threeDS2Container');

                if (type == "IdentifyShopper") {
                    self.threeDS2IdentifyComponent = self.checkout
                        .create('threeDS2DeviceFingerprint', {
                            fingerprintToken: token,
                            onComplete: function (result) {
                                self.threeDS2IdentifyComponent.unmount();
                                threeds2.processThreeDS2(result.data).done(function (responseJSON) {
                                    self.validateThreeDS2OrPlaceOrder(responseJSON)
                                }).fail(function (result) {
                                    errorProcessor.process(result, self.messageContainer);
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                });
                            },
                            onError: function (error) {
                                console.log(JSON.stringify(error));
                            }
                        });

                    self.threeDS2IdentifyComponent.mount(threeDS2Node);


                } else if (type == "ChallengeShopper") {
                    fullScreenLoader.stopLoader();

                    var popupModal = $('#threeDS2Modal').modal({
                        // disable user to hide popup
                        clickableOverlay: false,
                        // empty buttons, we don't need that
                        buttons: [],
                        modalClass: 'threeDS2Modal'
                    });


                    popupModal.modal("openModal");

                    self.threeDS2ChallengeComponent = self.checkout
                        .create('threeDS2Challenge', {
                            challengeToken: token,
                            onComplete: function (result) {
                                self.threeDS2ChallengeComponent.unmount();
                                self.closeModal(popupModal);

                                fullScreenLoader.startLoader();
                                threeds2.processThreeDS2(result.data).done(function (responseJSON) {
                                    self.validateThreeDS2OrPlaceOrder(responseJSON);
                                }).fail(function (result) {
                                    //save 3DSecure refuse reason in db & send quote data to Riskified
                                    var responseData = result.responseJSON,
                                        serviceUrl = window.location.origin + "/decider/order/deny",
                                        payload = {
                                            mode: 'adyen-cc-3DS-deny',
                                            quote_id: quote.getQuoteId(),
                                            reason: responseData.message,
                                            front_action: 'wrong password',
                                            gateway: "adyen_cc"
                                        };
                                    $.ajax({
                                        method: "POST",
                                        async: false,
                                        url: serviceUrl,
                                        data: payload
                                    });
                                }).error(function () {
                                    popupModal.modal("closeModal");
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                });
                            },
                            onError: function (error) {
                                console.log(JSON.stringify(error));
                            }
                        });
                    self.threeDS2ChallengeComponent.mount(threeDS2Node);
                }
            }
        };

        return function (target) {
            return target.extend(mixin);
        };
    }
);
