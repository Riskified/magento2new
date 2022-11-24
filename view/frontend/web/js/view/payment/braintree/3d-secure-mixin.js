/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'PayPal_Braintree/js/view/payment/adapter',
    'Magento_Checkout/js/model/quote',
    'Riskified_Decider/js/model/advice',
    'Magento_Checkout/js/model/full-screen-loader',
    'braintreeThreeDSecure',
    'mage/translate'
], function ($, braintree, quote, advice, fullScreenLoader, threeDSecure, $t) {
    'use strict';

        return function (braintreeThreedSecure) {
            braintreeThreedSecure.validate = function(context) {
                var clientInstance = braintree.getApiClient(),
                    state = $.Deferred(),
                    totalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2),
                    billingAddress = quote.billingAddress();

                if (billingAddress.regionCode == null) {
                    billingAddress.regionCode = undefined;
                }

                if (billingAddress.regionCode !== undefined && billingAddress.regionCode.length > 2) {
                    billingAddress.regionCode = undefined;
                }

                // No 3d secure if using CVV verification on vaulted cards
                if (quote.paymentMethod().method.indexOf('braintree_cc_vault_') !== -1) {
                    if (this.config.useCvvVault === true) {
                        state.resolve();
                        return state.promise();
                    }
                }

                if (!this.isAmountAvailable(totalAmount) || !this.isCountryAvailable(billingAddress.countryId)) {
                    state.resolve();
                    return state.promise();
                }

                var firstName = this.escapeNonAsciiCharacters(billingAddress.firstname);
                var lastName = this.escapeNonAsciiCharacters(billingAddress.lastname);

                let challengeRequested = this.getChallengeRequested();

                fullScreenLoader.startLoader();

                var setup3d = function(clientInstance) {
                    threeDSecure.create({
                        version: 2,
                        client: clientInstance
                    }, function (threeDSecureErr, threeDSecureInstance) {
                        if (threeDSecureErr) {
                            fullScreenLoader.stopLoader();
                            return state.reject($t('Please try again with another form of payment.'));
                        }

                        var threeDSContainer = document.createElement('div'),
                            tdmask = document.createElement('div'),
                            tdframe = document.createElement('div'),
                            tdbody = document.createElement('div');

                        threeDSContainer.id = 'braintree-three-d-modal';
                        tdmask.className ="bt-mask";
                        tdframe.className ="bt-modal-frame";
                        tdbody.className ="bt-modal-body";

                        tdframe.appendChild(tdbody);
                        threeDSContainer.appendChild(tdmask);
                        threeDSContainer.appendChild(tdframe);

                        threeDSecureInstance.verifyCard({
                            amount: totalAmount,
                            nonce: context.paymentMethodNonce,
                            challengeRequested: challengeRequested,
                            billingAddress: {
                                givenName: firstName,
                                surname: lastName,
                                phoneNumber: billingAddress.telephone,
                                streetAddress: billingAddress.street[0],
                                extendedAddress: billingAddress.street[1],
                                locality: billingAddress.city,
                                region: billingAddress.regionCode,
                                postalCode: billingAddress.postcode,
                                countryCodeAlpha2: billingAddress.countryId
                            },
                            onLookupComplete: function (data, next) {
                                next();
                            },
                            addFrame: function (err, iframe) {
                                fullScreenLoader.stopLoader();

                                if (err) {
                                    console.log("Unable to verify card over 3D Secure", err);
                                    return state.reject($t('Please try again with another form of payment.'));
                                }

                                tdbody.appendChild(iframe);
                                document.body.appendChild(threeDSContainer);
                            },
                            removeFrame: function () {
                                fullScreenLoader.startLoader();
                                document.body.removeChild(threeDSContainer);
                            }
                        }, function (err, response) {
                            fullScreenLoader.stopLoader();

                            try {
                                let additionalPayload = {
                                    nonce: response.nonce,
                                    liabilityShiftPossible: response.liabilityShiftPossible,
                                    liabilityShifted: response.liabilityShifted
                                };

                                advice
                                    .setGateway('braintree_cc')
                                    .setAdditionalPayload(additionalPayload)
                                    .deny();
                            } catch (e) {
                                return false;
                            }

                            if (err) {
                                console.error("3dsecure validation failed", err);

                                if (err.code === 'THREEDS_LOOKUP_VALIDATION_ERROR') {
                                    let errorMessage = err.details.originalError.details.originalError.error.message;
                                    if (errorMessage === 'Billing line1 format is invalid.' && billingAddress.street[0].length > 50) {
                                        return state.reject(
                                            $t('Billing line1 must be string and less than 50 characters. Please update the address and try again.')
                                        );

                                    } else if (errorMessage === 'Billing line2 format is invalid.' && billingAddress.street[1].length > 50) {
                                        return state.reject(
                                            $t('Billing line2 must be string and less than 50 characters. Please update the address and try again.')
                                        );
                                    }
                                    return state.reject($t(errorMessage));
                                } else {
                                    return state.reject($t('Please try again with another form of payment.'));
                                }
                            }

                            var liability = {
                                shifted: response.liabilityShifted,
                                shiftPossible: response.liabilityShiftPossible
                            };

                            if (liability.shifted || !liability.shifted && !liability.shiftPossible) {
                                context.paymentMethodNonce = response.nonce;
                                state.resolve();
                            } else {
                                state.reject($t('Please try again with another form of payment.'));
                            }
                        });

                        // When customer cancel 3d secure popup, invalidate the re-captcha v2.
                        var isReCaptchaEnabled = window.checkoutConfig.recaptcha_braintree;
                        if (isReCaptchaEnabled) {
                            var recaptchaCheckBox = jQuery("#recaptcha-checkout-braintree-wrapper input[name='recaptcha-validate-']");

                            threeDSecureInstance.on('customer-canceled', function () {
                                if (recaptchaCheckBox.prop('checked') === true) {
                                    recaptchaCheckBox.prop('checked', false);
                                }
                            });
                        }
                    });
                };

                if (!clientInstance) {
                    require(['PayPal_Braintree/js/view/payment/method-renderer/cc-form'], function(c) {
                        var config = c.extend({
                            defaults: {
                                clientConfig: {
                                    onReady: function() {}
                                }
                            }
                        });
                        braintree.setConfig(config.defaults.clientConfig);
                        braintree.setup(setup3d);
                    });
                } else {
                    setup3d(clientInstance);
                }

                return state.promise();
            };

            braintreeThreedSecure.isCountryAvailable = function(countryId) {
                var key,
                    specificCountries = this.config.specificCountries;

                // all countries are available
                if (!specificCountries.length) {
                    return true;
                }

                for (key in specificCountries) {
                    if (countryId === specificCountries[key]) {
                        return true;
                    }
                }

                return false;
            };

            return braintreeThreedSecure;
        };
});
