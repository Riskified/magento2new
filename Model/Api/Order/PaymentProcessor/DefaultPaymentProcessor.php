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
        $details['cvv_result_code'] = $this->payment->getCcCidStatus() !== null ?
            $this->payment->getCcCidStatus() : $this->payment->getAdditionalInformation('cvvResultCode');
        $details['avs_result_code'] = $this->payment->getCcAvsStatus();
        $details['credit_card_company'] = $this->payment->getCcType() !== null ?
            $this->payment->getCcType() : $this->payment->getAdditionalInformation('cardType');
        $details['credit_card_number'] = $this->payment->getCcLast4();

        return $details;
    }
}
