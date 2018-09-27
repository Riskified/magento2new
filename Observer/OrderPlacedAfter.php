<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class OrderPlacedAfter implements ObserverInterface
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
     * OrderPlacedAfter constructor.
     *
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
        $order = $observer->getOrder();

        if (!$order) {
            return;
        }

        if ($order->dataHasChangedFor('state')) {
            try {
                $this->_orderApi->post($order, Api::ACTION_UPDATE);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        } else {
            $this->_logger->debug(__("No data found"));
        }
    }
}
