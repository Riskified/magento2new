<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;

class ProcessSuccessfulPost implements ObserverInterface
{
    /**
     * @var \Riskified\Decider\Logger\Order
     */
    private $logger;

    /**
     * @var \Riskified\Decider\Api\Order
     */
    private $orderApi;

    /**
     * ProcessSuccessfulPost constructor.
     * @param \Riskified\Decider\Logger\Order $logger
     * @param \Riskified\Decider\Api\Order $orderApi
     */
    public function __construct(
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Order $orderApi
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
