<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Cryozonic extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $details['credit_card_number'] = $this->payment->getCcLast4();
        $details['credit_card_company'] = $this->payment->getCcType();
        $details['avs_result_code'] =
            $this->payment->getAdditionalInformation('address_line1_check')
            . ','
            . $this->payment->getAdditionalInformation('address_zip_check');

        return $details;
    }
}
