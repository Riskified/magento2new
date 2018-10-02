<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

class Vantiv extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $details['credit_card_number'] = $this->payment->getAdditionalInformation('last_four');
        $details['credit_card_company'] = $this->payment->getCcType();

        $transactionAdditionalInfo = $this->payment->getTransactionAdditionalInfo();
        if (isset($transactionAdditionalInfo['raw_details_info']['avsResult'])) {
            $details['avs_result_code'] = $transactionAdditionalInfo['raw_details_info']['avsResult'];
        }
        if (isset($transactionAdditionalInfo['raw_details_info']['tokenBin'])) {
            $details['credit_card_bin'] = $transactionAdditionalInfo['raw_details_info']['tokenBin'];
        }

        return $details;
    }
}
