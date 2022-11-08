<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

use Riskified\Decider\Model\Gateway\Braintree\Response\ThreeDSecureDetailsHandler as DeciderThreeDSecureDetails;

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

        if ($this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::ECI)) {
            $details['eci'] = $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::ECI);
            $details['trans_status'] = $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::TRANS_STATUS);
            $details['liability_shift'] = $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::LIABILITY_SHIFTED);
        }

        $houseVerification = $this->payment->getAdditionalInformation('avsStreetAddressResponseCode');
        $zipVerification = $this->payment->getAdditionalInformation('avsPostalCodeResponseCode');
        $details['avs_result_code'] = $houseVerification . ',' . $zipVerification;

        return $details;
    }
}
