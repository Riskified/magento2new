/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/**
 * @api
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url'
], function ($, quote, urlBuilder) {
    'use strict';

    return {
        payload : {},
        additionalPayload : {},
        setGateway: function(gateway) {
          this.gateway = gateway;

          return this;
        },
        setMode: function(mode) {
          this.mode = mode;

            return this;
        },
        preparePayload : function() {
            let payload = {
                quote_id: quote.getQuoteId(),
                email: quote.guestEmail,
                gateway: this.gateway
            };

            this.payload = {
                ...payload,
                ...this.additionalPayload
            };
        },
        setAdditionalPayload : function(payload) {
            this.additionalPayload = payload;

            return this;
        },
        registerSuccessCallback : function(callback) {
            this.successCallback = callback;

            return this;
        },
        registerDenyCallback : function(callback) {
            this.denyCallback = callback;

            return this;
        },
        registerFailedCallback : function(callback) {
            this.failedCallback = callback;

            return this;
        },
        registerDisabledCallback : function(callback) {
            this.disableCallback = callback;

            return this;
        },
        validate : function() {
            this.preparePayload();

            return this.doCall("/decider/advice/call", this.payload).success((response) => {
                let apiResponseStatus = response.status;
                if (apiResponseStatus === 0){
                    if (this.successCallback) {
                        this.successCallback();
                    }
                } else {
                    if (apiResponseStatus === 3){
                        if (this.denyCallback) {
                            this.denyCallback();
                        }
                    } else if (apiResponseStatus === 9999){
                        if (this.disableCallback) {
                            this.disableCallback();
                        }
                    } else if (apiResponseStatus === 1){
                        if (this.failedCallback) {
                            this.failedCallback();
                        }
                    } else {
                        if (this.successCallback) {
                            this.successCallback();
                        }
                    }
                }
            });
        },
        deny : function() {
            return this.doCall("decider/advice/deny", this.payload);
        },
        doCall : function(url, payload) {
            return $.ajax({
                url: urlBuilder.build(url),
                type: 'POST',
                data: payload,
                async: false,
                contentType: "application/json; charset=utf-8"
            });
        }
    };
});
