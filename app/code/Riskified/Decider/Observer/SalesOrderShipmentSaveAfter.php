<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class SalesOrderShipmentSaveAfter implements ObserverInterface
{
    private $apiOrderLayer;

    public function __construct(
        \Riskified\Decider\Api\Order $orderApi
    )
    {
        $this->apiOrderLayer = $orderApi;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getShipment();
        $this->apiOrderLayer->post($shipment->getOrder(), Api::ACTION_FULFILL);
    }
}
