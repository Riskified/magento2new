<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Checkoutcom extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $data = $this->payment->getAdditionalInformation();
        $details['credit_card_number'] = $data['cko_payment_information']['source']['last4'];
        $details['cvv_result_code'] = $data['cko_payment_information']['source']['cvv_check'];
        $details['credit_card_bin'] = $data['cko_payment_information']['source']['bin'];
        $details['avs_result_code'] = $data['cko_payment_information']['source']['avs_check'];

        return $details;
    }
}
