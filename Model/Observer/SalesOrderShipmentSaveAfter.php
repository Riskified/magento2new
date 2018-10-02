<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;

class SalesOrderShipmentSaveAfter implements ObserverInterface
{
    /**
     * @var OrderApi
     */
    private $apiOrderLayer;

    /**
     * SalesOrderShipmentSaveAfter constructor.
     *
     * @param OrderApi $orderApi
     */
    public function __construct(
        OrderApi $orderApi
    ) {
        $this->apiOrderLayer = $orderApi;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getShipment();
        $this->apiOrderLayer->post($shipment->getOrder(), Api::ACTION_FULFILL);
    }
}
