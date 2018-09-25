<?php

namespace Riskified\Decider\Api\Order\PaymentProcessor;

class DirectPost extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $authorize_data = $this->payment->getAdditionalInformation('authorize_cards');
        if (!$authorize_data || !is_array($authorize_data)) {
            return $details;
        }

        $cards_data = array_values($authorize_data);
        if ($cards_data && $cards_data[0]) {
            $card_data = $cards_data[0];
            if (isset($card_data['cc_last4'])) {
                $details['credit_card_number'] = $this->payment->decrypt($card_data['cc_last4']);
            }
            if (isset($card_data['cc_type'])) {
                $details['credit_card_company'] = $card_data['cc_type'];
            }
            if (isset($card_data['cc_avs_result_code'])) {
                $details['avs_result_code'] = $card_data['cc_avs_result_code'];
            }
            if (isset($card_data['cc_response_code'])) {
                $details['cvv_result_code'] = $card_data['cc_response_code'];
            }
        }

        $details['credit_card_number'] = $this->payment->decrypt($this->payment->getCcLast4());

        return $details;
    }
}
