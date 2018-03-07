<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class OrderPaymentCancel implements ObserverInterface
{
    private $logger;
    private $apiOrderLayer;

    public function __construct(
        \Riskified\Decider\Api\Log $logger,
        \Riskified\Decider\Api\Order $orderApi
    ) {
        $this->logger = $logger;
        $this->apiOrderLayer = $orderApi;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getPayment()->getOrder();
        try {
            $this->apiOrderLayer->post($order, Api::ACTION_CANCEL);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }
}
