<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class DefaultPaymentProcessor extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $details['cvv_result_code'] = $this->payment->getCcCidStatus();
        $details['avs_result_code'] = $this->payment->getCcAvsStatus();
        $details['credit_card_company'] = $this->payment->getCcType();
        $details['credit_card_number'] = $this->payment->getCcLast4();

        return $details;
    }
}
