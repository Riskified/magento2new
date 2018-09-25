<?php

namespace Riskified\Decider\Api\Order\PaymentProcessor;

class PaypalDirect extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $details['avs_result_code'] = $this->payment->getAdditionalInformation('paypal_avs_code');
        $details['cvv_result_code'] = $this->payment->getAdditionalInformation('paypal_cvv2_match');
        $details['credit_card_number'] = $this->payment->getCcLast4();
        $details['credit_card_company'] = $this->payment->getCcType();

        return $details;
    }
}
