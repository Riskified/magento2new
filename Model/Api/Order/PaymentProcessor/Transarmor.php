<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Transarmor extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $details['avs_result_code'] = $this->payment->getAdditionalInformation('avs_response');
        $details['cvv_result_code'] = $this->payment->getAdditionalInformation('cvv2_response');

        return $details;
    }
}
