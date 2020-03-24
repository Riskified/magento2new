<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Api\Log as LogApi;

class SalesOrderShipmentSaveAfter implements ObserverInterface
{
    /**
     * @var LogApi
     */
    private $logger;

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
        LogApi $logger,
        OrderApi $orderApi
    ) {
        $this->logger = $logger;
        $this->apiOrderLayer = $orderApi;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getShipment();
        $order = $shipment->getOrder();
        $currentOrderItems = $order->getItemsCollection();
        $itemsToShip = $shipment->getItems();
        $ids = array();
        //collecting item ids to be shipped only
        foreach($itemsToShip as $item) {
            array_push($ids, $item->getOrderItemId());
        }
        //remove items that are not suppose tobe shipped
        foreach($currentOrderItems as $orderItem) {
            if(!in_array($orderItem->getItemId(), $ids)) {
                $currentOrderItems->removeItemByKey($orderItem->getItemId());
            }
        }
        $order->setItems($currentOrderItems);
        $this->apiOrderLayer->post($order, Api::ACTION_FULFILL);
    }
}
