<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Riskified\Decider\Model\Api\Order as ApiOrder;

class CollectPaymentInfo implements ObserverInterface
{
    /**
     * @var OrderLogger
     */
    private $_logger;

    /**
     * @var ApiOrder
     */
    private $_orderApi;

    /**
     * CollectPaymentInfo constructor.
     *
     * @param OrderLogger $logger
     * @param ApiOrder $orderApi
     */
    public function __construct(
        OrderLogger $logger,
        ApiOrder $orderApi
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
