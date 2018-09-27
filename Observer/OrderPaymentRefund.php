<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class OrderPaymentRefund implements ObserverInterface
{
    /**
     * @var \Riskified\Decider\Api\Log
     */
    private $logger;

    /**
     * @var \Riskified\Decider\Api\Order
     */
    private $apiOrderLayer;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    /**
     * OrderPaymentRefund constructor.
     * @param \Riskified\Decider\Api\Log $logger
     * @param \Riskified\Decider\Api\Order $orderApi
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Riskified\Decider\Api\Log $logger,
        \Riskified\Decider\Api\Order $orderApi,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->apiOrderLayer = $orderApi;
        $this->messageManager = $messageManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order = $observer->getPayment()->getOrder();
            $this->apiOrderLayer->post($order, Api::ACTION_CANCEL);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __("Riskified API Respond : %1", $e->getMessage())
            );
            $this->logger->logException($e);
        }
    }
}
