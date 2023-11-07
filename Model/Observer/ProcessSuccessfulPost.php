<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
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
    private $registry;

    /**
     * ProcessSuccessfulPost constructor.
     * @param OrderLogger $logger
     * @param OrderApi $orderApi
     */
    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi,
        Registry $registry
    ) {
        $this->logger = $logger;
        $this->orderApi = $orderApi;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry("post-fullfillment")) {
            return $this;
        }
        
        $order = $observer->getOrder();
        $response = $observer->getResponse();
        if (isset($response->order)) {
            $orderId = $response->order->id;
            $status = $response->order->status;
            $oldStatus = $response->order->old_status ?? null;
            $description = $response->order->description ?? null;

            if (!$description) {
                $description = "Riskified Status: $status";
            }

            if ($orderId && $status) {
                $this->orderApi->update($order, $status, $oldStatus, $description);
            }
        }
    }
}
