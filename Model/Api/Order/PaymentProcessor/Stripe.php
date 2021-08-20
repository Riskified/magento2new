<?php
/**
 * Stripe payment processor 
 * 
 */
declare(strict_types = 1);

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;
use Magento\Framework\ObjectManagerInterface;

/**
 * @class Stripe
 * 
 * @description Handle payment data processed and returned by Stripe extension.
 */
class Stripe extends AbstractPayment
{
    /**
     * @inheritdoc
     * 
     * @return array
     */
    public function getDetails() : array
    {
   
        $details = [];
        $jsonEncodedSource = $this->payment->getAdditionalInformation('source_info');
        $last4 = $this->payment->getCcLast4();
        $ccCompany = $this->payment->getCcType();

        if ($jsonEncodedSource) {
            $sourceInfo = json_decode($jsonEncodedSource);
            $parts = explode(' ', $sourceInfo['Card']);

            $ccCompany = $parts[0];
            $last4 = $parts[3];
        }

        $details['credit_card_number'] = $last4;
        $details['credit_card_company'] = $ccCompany;

        return $details;
    }
}
