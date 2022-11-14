/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Braintree/js/view/payment/adapter',
    'Magento_Checkout/js/model/quote',
    'Riskified_Decider/js/model/advice',
    'mage/translate'
], function ($, braintree, quote, advice, $t) {
    'use strict';

        return function (braintreeThreedSecure) {
            braintreeThreedSecure.validate = function(context) {
                var client = braintree.getApiClient(),
                    state = $.Deferred(),
                    totalAmount = quote.totals()['base_grand_total'],
                    billingAddress = quote.billingAddress();

                if (!this.isAmountAvailable(totalAmount) || !this.isCountryAvailable(billingAddress.countryId)) {
                    state.resolve();

                    return state.promise();
                }

                client.verify3DS({
                    amount: totalAmount,
                    creditCard: context.paymentMethodNonce
                }, function (error, response) {
                    var liability;

                    if (error) {
                        state.reject(error.message);

                        return;
                    }

                    liability = {
                        shifted: response.verificationDetails.liabilityShifted,
                        shiftPossible: response.verificationDetails.liabilityShiftPossible
                    };

                    if (liability.shifted || !liability.shifted && !liability.shiftPossible) {
                        context.paymentMethodNonce = response.nonce;
                        state.resolve();
                    } else {
                        //saving 3D Secure Refuse reason in db.
                        try {
                            let additionalPayload = {
                                nonce: response.nonce,
                                liabilityShiftPossible: response.verificationDetails.liabilityShiftPossible,
                                liabilityShifted: response.verificationDetails.liabilityShifted
                            };

                            advice
                                .setGateway('braintree_cc')
                                .setAdditionalPayload(additionalPayload)
                                .deny();
                        } catch (e) {
                            return false;
                        }

                        state.reject($t('Please try again with another form of payment.'));
                    }
                });

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
