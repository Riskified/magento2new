<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Authcim extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $details['avs_result_code'] = $this->payment->getAdditionalInformation('avs_result_code');
        $details['cvv_result_code'] = $this->payment->getAdditionalInformation('card_code_response_code');
        $details['credit_card_bin'] = $this->payment->getAdditionalInformation('riskified_cc_bin');

        return $details;
    }
}
