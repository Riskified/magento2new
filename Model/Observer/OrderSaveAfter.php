<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Riskified\Decider\Model\Api\Order as OrderApi;

class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var OrderLogger
     */
    private $_logger;

    /**
     * @var OrderApi
     */
    private $_orderApi;

    /**
     * OrderSaveAfter constructor.
     *
     * @param OrderLogger $logger
     * @param OrderApi $orderApi
     */
    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi
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
            if ($order->getPayment()->getMethod() == 'authorizenet_directpost') {
                try {
                    $this->_orderApi->post($order, Api::ACTION_UPDATE);
                } catch (\Exception $e) {
                    $this->_logger->critical($e);
                }
            }
        } else {
            $this->_logger->debug(__("No data found"));
        }
    }
}
