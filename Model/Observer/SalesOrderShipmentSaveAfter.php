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

        try {
            $this->apiOrderLayer->post($shipment, Api::ACTION_FULFILL);
        } catch(\Exception $e) {
            $this->logger->log(
                sprintf(
                    __("Order fulfilment was not able to sent. Order #%s, shipment #%s"),
                    $shipment->getOrder()->getIncrementId(),
                    $shipment->getIncrementId()
                )
            );
        }
    }
}
