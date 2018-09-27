<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;

class CollectPaymentInfo implements ObserverInterface
{
    /**
     * @var \Riskified\Decider\Logger\Order
     */
    private $_logger;
    /**
     * @var \Riskified\Decider\Api\Order
     */
    private $_orderApi;

    /**
     * CollectPaymentInfo constructor.
     * @param \Riskified\Decider\Logger\Order $logger
     * @param \Riskified\Decider\Api\Order $orderApi
     */
    public function __construct(
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Order $orderApi
    ) {
        $this->_logger = $logger;
        $this->_orderApi = $orderApi;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return;
        $payment = $observer->getQuote()->getPayment();
        $cc_bin = substr($payment->getCcNumber(), 0, 6);
        if ($cc_bin) {
            $payment->setAdditionalInformation('riskified_cc_bin', $cc_bin);
        }
    }
}
