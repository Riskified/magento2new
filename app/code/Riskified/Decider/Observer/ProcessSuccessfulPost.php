<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class ProcessSuccessfulPost implements ObserverInterface
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
        $order = $observer->getOrder();
        $response = $observer->getResponse();
        if (isset($response->order)) {
            $orderId = $response->order->id;
            $status = $response->order->status;
            $oldStatus = isset($response->order->old_status) ? $response->order->old_status : null;
            $description = isset($response->order->description) ? $response->order->description : null;

            if (!$description) {
                $description = "Riskified Status: $status";
            }

            if ($orderId && $status) {
                $this->_orderApi->update($order, $status, $oldStatus, $description);
            }
        }
    }
}
