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

        $eci = $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::ECI);
        if ($eci) {
            $details['authentication_result'] = array_filter([
                'eci' => $eci,
                'liability_shift' => (bool) $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::LIABILITY_SHIFTED),
                'trans_status' => $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::TRANS_STATUS) ?: null,
                'cavv' => $this->payment->getAdditionalInformation(DeciderThreeDSecureDetails::CAVV) ?: null,
            ], fn ($v) => $v !== null);
        }

        $houseVerification = $this->payment->getAdditionalInformation('avsStreetAddressResponseCode');
        $zipVerification = $this->payment->getAdditionalInformation('avsPostalCodeResponseCode');
        $details['avs_result_code'] = $houseVerification . ',' . $zipVerification;

        return $details;
    }
}
