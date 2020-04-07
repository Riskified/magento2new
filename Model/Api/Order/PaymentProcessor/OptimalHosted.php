<?php

namespace Riskified\Decider\Model\Api\Order\PaymentProcessor;

use Riskified\Decider\Model\Api\Order\Log;

class OptimalHosted extends AbstractPayment
{
    /**
     * @var Log
     */
    private $logger;

    /**
     * OptimalHosted constructor.
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

        try {
            $optimalTransaction = unserialize($this->payment->getAdditionalInformation('transaction'));
            $houseVerification = $optimalTransaction->houseNumberVerification;
            $zipVerification = $optimalTransaction->zipVerification;

            $details['avs_result_code'] = $houseVerification . ',' . $zipVerification;
            $details['cvv_result_code'] = $optimalTransaction->cvdVerification;
        } catch (\Exception $e) {
            $this->logger->log(__(
                'optimal payment additional payment info failed to parse: %1',
                $e->getMessage()
            ));
        }

        return $details;
    }
}
