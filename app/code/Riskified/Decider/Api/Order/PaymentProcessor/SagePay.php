<?php

namespace Riskified\Decider\Api\Order\PaymentProcessor;

class SagePay extends AbstractPayment
{
    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];

        $sage = $this->order->getSagepayInfo();
        if ($sage) {
            $details['avs_result_code'] = $sage->getData('address_result');
            $details['cvv_result_code'] = $sage->getData('cv2result');
            $details['credit_card_number'] = $sage->getData('last_four_digits');
            $details['credit_card_company'] = $sage->getData('card_type');
        }

        return $details;
    }
}
