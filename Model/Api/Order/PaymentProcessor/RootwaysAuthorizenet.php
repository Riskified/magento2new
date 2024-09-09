<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

use Riskified\Decider\Model\Api\Order\Log;

class RootwaysAuthorizenet extends AbstractPayment
{
    /**
     * @var Log
     */
    private $logger;

    /**
     * RootwaysAuthorizenet constructor.
     *
     * @param Log $logger
     */
    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getDetails()
    {
        $details = [];
        $details['credit_card_bin'] = $this->payment->getAdditionalInformation('cc_bin');

        return $details;
    }
}
