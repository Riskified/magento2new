<?php
namespace Riskified\Full\Api;

class Log {
    private function payment($model) {
        Mage::helper('full/log')->log("Payment info debug Logs:");
        try {
            $payment = $model->getPayment();
            $gateway_name = $payment->getMethod();
            Mage::helper('full/log')->log("Payment Gateway: ".$gateway_name);
            Mage::helper('full/log')->log("payment->getCcLast4(): ".$payment->getCcLast4());
            Mage::helper('full/log')->log("payment->getCcType(): ".$payment->getCcType());
            Mage::helper('full/log')->log("payment->getCcCidStatus(): ".$payment->getCcCidStatus());
            Mage::helper('full/log')->log("payment->getCcAvsStatus(): ".$payment->getCcAvsStatus());
            Mage::helper('full/log')->log("payment->getAdditionalInformation(): ".PHP_EOL.var_export($payment->getAdditionalInformation(), 1));
            # paypal_avs_code,paypal_cvv2_match,paypal_fraud_filters,avs_result,cvv2_check_result,address_verification,
            # postcode_verification,payment_status,pending_reason,payer_id,payer_status,email,credit_card_cvv2,
            # cc_avs_status,cc_approval,cc_last4,cc_owner,cc_exp_month,cc_exp_year,
            $sage = $model->getSagepayInfo();
            if(is_object($sage)) {
                #####,postcode_result,avscv2,address_status,payer_status
                Mage::helper('full/log')->log("sagepay->getLastFourDigits(): ".$sage->getLastFourDigits());
                Mage::helper('full/log')->log("sagepay->last_four_digits: ".$sage->getData('last_four_digits'));
                Mage::helper('full/log')->log("sagepay->getCardType(): ".$sage->getCardType());
                Mage::helper('full/log')->log("sagepay->card_type: ".$sage->getData('card_type'));
                Mage::helper('full/log')->log("sagepay->getAvsCv2Status: ".$sage->getAvsCv2Status());
                Mage::helper('full/log')->log("sagepay->address_result: ".$sage->getData('address_result'));
                Mage::helper('full/log')->log("sagepay->getCv2result: ".$sage->getCv2result());
                Mage::helper('full/log')->log("sagepay->cv2result: ".$sage->getData('cv2result'));
                Mage::helper('full/log')->log("sagepay->getAvscv2: ".$sage->getAvscv2());
                Mage::helper('full/log')->log("sagepay->getAddressResult: ".$sage->getAddressResult());
                Mage::helper('full/log')->log("sagepay->getPostcodeResult: ".$sage->getPostcodeResult());
                Mage::helper('full/log')->log("sagepay->getDeclineCode: ".$sage->getDeclineCode());
                Mage::helper('full/log')->log("sagepay->getBankAuthCode: ".$sage->getBankAuthCode());
                Mage::helper('full/log')->log("sagepay->getPayerStatus: ".$sage->getPayerStatus());
            }
            if($gateway_name == "optimal_hosted") {
                $optimalTransaction = unserialize($payment->getAdditionalInformation('transaction'));
                if($optimalTransaction) {
                    Mage::helper('full/log')->log("Optimal transaction: ");
                    Mage::helper('full/log')->log("transaction->cvdVerification: ".$optimalTransaction->cvdVerification);
                    Mage::helper('full/log')->log("transaction->houseNumberVerification: ".$optimalTransaction->houseNumberVerification);
                    Mage::helper('full/log')->log("transaction->zipVerification: ".$optimalTransaction->zipVerification);
                }
                else {
                    Mage::helper('full/log')->log("Optimal gateway but no transaction found");
                }
            }
        } catch(Exception $e) {
            Mage::helper('full/log')->logException($e);
        }
    }
}