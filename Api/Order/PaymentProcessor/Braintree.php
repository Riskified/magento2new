<?php

namespace Riskified\Decider\Api\Order\PaymentProcessor;

class Braintree extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $details['cvv_result_code'] = $this->payment->getAdditionalInformation('cvvResponseCode');
        $details['credit_card_bin'] = $this->payment->getAdditionalInformation('bin');

        $houseVerification = $this->payment->getAdditionalInformation('avsStreetAddressResponseCode');
        $zipVerification = $this->payment->getAdditionalInformation('avsPostalCodeResponseCode');
        $details['avs_result_code'] = $houseVerification . ',' . $zipVerification;

        return $details;
    }
}
