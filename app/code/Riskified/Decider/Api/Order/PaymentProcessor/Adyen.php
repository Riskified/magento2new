<?php

namespace Riskified\Decider\Api\Order\PaymentProcessor;

class Adyen extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $details['avs_result_code'] = $this->payment->getAdditionalInformation('adyen_avs_result');
        $details['cvv_result_code'] = $this->payment->getAdditionalInformation('adyen_cvc_result');
        $details['transaction_id'] = $this->payment->getAdditionalInformation('pspReference');
        $details['credit_card_bin'] = $this->payment->getAdyenCardBin();

        return $details;
    }
}
