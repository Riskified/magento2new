<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Paypal extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $details['payer_email'] = $this->payment->getAdditionalInformation('paypal_payer_email');
        $details['payer_status'] = $this->payment->getAdditionalInformation('paypal_payer_status');
        $details['payer_address_status'] = $this->payment->getAdditionalInformation('paypal_address_status');
        $details['protection_eligibility'] = $this->payment->getAdditionalInformation('paypal_protection_eligibility');
        $details['payment_status'] = $this->payment->getAdditionalInformation('paypal_payment_status');
        $details['pending_reason'] = $this->payment->getAdditionalInformation('paypal_pending_reason');

        return $details;
    }
}
