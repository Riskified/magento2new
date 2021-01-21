<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class AdyenHpp extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        if (strtolower($this->payment->getCcType()) == "paypal") {
            $details['payer_email'] = $this->payment->getAdditionalInformation('paypal_payer_email');
            $details['payer_status'] = $this->payment->getAdditionalInformation('paypal_payer_status');
            $details['payer_address_status'] = $this->payment->getAdditionalInformation('paypal_address_status');
            $details['protection_eligibility'] = $this->payment->getAdditionalInformation('paypal_protection_eligibility');
            $details['payment_status'] = $this->payment->getAdditionalInformation('paypal_payment_status');
            $details['pending_reason'] = $this->payment->getAdditionalInformation('paypal_pending_reason');
        } else {
            $details['avs_result_code'] = $this->payment->getAdditionalInformation('adyen_avs_result');
            $details['cvv_result_code'] = $this->payment->getAdditionalInformation('adyen_cvc_result');
            $details['transaction_id'] = $this->payment->getAdditionalInformation('pspReference');
            $details['credit_card_bin'] = $this->payment->getAdditionalInformation('adyen_card_bin');
        }
        return $details;
    }
}
