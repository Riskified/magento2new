<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

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
        $details['credit_card_bin'] = $this->payment->getAdditionalInformation('adyen_card_bin');
        
        $authResult = $this->payment->getAdditionalInformation('additionalData');
        $threeds2 = $this->payment->getAdditionalInformation('threeds2');

        if ($authResult && is_array($authResult)) {
            $details['authentication_result'] = [
                'eci' => $authResult['eci'] ?? null,
                'liability_shift' => $authResult['liabilityShift'] ?? null,
                'trans_status' => $authResult['trans_status'] ?? ($authResult['threeDAuthenticatedResponse'] ?? null),
                'trans_status_reason' => $authResult['trans_status_reason'] ?? null,
                'cavv' => $authResult['cavv'] ?? null,
                'xid' => $authResult['xid'] ?? null,
                'ds_trans_id' => $authResult['ds_trans_id'] ?? ($authResult['dsTransID'] ?? null),
                'three_ds_version' => $authResult['three_ds_version'] ?? ($authResult['threeDSVersion'] ?? null),
                'three_ds_active' => $authResult['three_ds_active'] ?? null,
                'authorisation_token' => $authResult['authorisation_token'] ?? null,
                'payment_data' => $authResult['payment_data'] ?? null,
            ];
        }

        if ($threeds2 && is_array($threeds2)) {
            $details['authentication_result'] = [
                'eci' => $threeds2['threeds2.threeDS2Result.eci'] ?? null,
                'trans_status' => $threeds2['threeds2.threeDS2Result.transStatus'] ?? null,
                'cavv' => $threeds2['cavv'] ?? null,
                'ds_trans_id' => $threeds2['dsTransID'] ?? null,
            ];
        }

        $details['authorization_error'] = [
            'error_code' => $authResult['refusalReasonCode'] ?? null,
            'message' => $authResult['refusalReason'] ?? null,
        ];
        dd($threeds2, $details);
        return $details;
    }
}
