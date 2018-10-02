<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Riskified\Decider\Model\Api\Order as OrderApi;

class ProcessSuccessfulPost implements ObserverInterface
{
    /**
     * @var OrderLogger
     */
    private $logger;

    /**
     * @var OrderApi
     */
    private $orderApi;

    /**
     * ProcessSuccessfulPost constructor.
     * @param OrderLogger $logger
     * @param OrderApi $orderApi
     */
    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi
    ) {
        $this->logger = $logger;
        $this->orderApi = $orderApi;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();
        $response = $observer->getResponse();
        if (isset($response->order)) {
            $orderId = $response->order->id;
            $status = $response->order->status;
            $oldStatus = isset($response->order->old_status) ? $response->order->old_status : null;
            $description = $response->order->description;

            if (!$description) {
                $description = "Riskified Status: $status";
            }

            if ($orderId && $status) {
                $this->orderApi->update($order, $status, $oldStatus, $description);
            }
        }
    }
}
