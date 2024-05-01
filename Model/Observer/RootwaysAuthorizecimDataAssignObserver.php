<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class RootwaysAuthorizecimDataAssignObserver implements ObserverInterface
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        $data = $observer->getData('data');
        $payment = $observer->getData('payment_model');
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $ccNumber = $additionalData['cc_number'] ?? null;

        if ($ccNumber) {
            $payment->setAdditionalInformation(
                'cc_bin',
                substr($ccNumber, 0, 6)
            );
        }
    }
}
