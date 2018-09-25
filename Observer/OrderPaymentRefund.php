<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class OrderPaymentRefund implements ObserverInterface
{
    private $logger;
    private $apiOrderLayer;
    private $messageManager;

    public function __construct(
        \Riskified\Decider\Api\Log $logger,
        \Riskified\Decider\Api\Order $orderApi,
        \Magento\Framework\Message\ManagerInterface $messageManager
    )
    {
        $this->logger = $logger;
        $this->apiOrderLayer = $orderApi;
        $this->messageManager = $messageManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order = $observer->getPayment()->getOrder();
            $this->apiOrderLayer->post($order, Api::ACTION_CANCEL);
        } catch(\Exception $e) {
            $this->messageManager->addErrorMessage(
                __("Riskified API Respond : %1", $e->getMessage())
            );
            $this->logger->logException($e);
        }
    }
}
