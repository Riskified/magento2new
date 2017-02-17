<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class SalesOrderShipmentSaveAfter implements ObserverInterface
{
    private $_logger;
    private $_orderApi;

    public function __construct(
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Order $orderApi
    )
    {
        $this->_logger = $logger;
        $this->_orderApi = $orderApi;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getShipment();
        $this->apiOrderLayer->post($shipment->getOrder(), Api::ACTION_FULFILL);
    }
}
