<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Payflowpro extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $cc_details = $this->payment->getAdditionalInformation('cc_details');
        $details['credit_card_number'] = $cc_details['cc_last_4'];
        $details['credit_card_company'] = $cc_details['cc_type'];
        $details['cvv_result_code'] = $this->payment->getAdditionalInformation('cvv2match');

        $houseVerification = $this->payment->getAdditionalInformation('avsaddr');
        $zipVerification = $this->payment->getAdditionalInformation('avszip');
        $details['avs_result_code'] = $houseVerification . ',' . $zipVerification;

        return $details;
    }
}
