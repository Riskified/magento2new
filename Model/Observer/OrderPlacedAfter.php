<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Riskified\Decider\Model\Api\Order as OrderApi;

class OrderPlacedAfter implements ObserverInterface
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
     * @var Registry
     */
    private $registry;

    /**
     * OrderPlacedAfter constructor.
     *
     * @param OrderLogger $logger
     * @param OrderApi $orderApi
     */
    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi,
        Registry $registry
    ) {
        $this->_logger = $logger;
        $this->_orderApi = $orderApi;
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();

        /** @var $order OrderInterface */
        if (!$order) {
            return;
        }

        if ($order->getPayment()->getMethod() == "flxpayment" || $order->getPayment()->getMethod() == "adyen_cc") {
            return;
        }

        if ($order->dataHasChangedFor('state')) {
            try {
                $this->registry->register("riskified-order", $order);
                $this->registry->register("riskified-place-order-after", true, true);

                $this->_orderApi->post($order, Api::ACTION_UPDATE);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        } else {
            $this->_logger->debug(__("No data found"));
        }
    }
}
