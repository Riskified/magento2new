<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Log as LogApi;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Magento\Framework\Message\ManagerInterface;

class OrderPaymentRefund implements ObserverInterface
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
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * OrderPaymentRefund constructor.
     *
     * @param LogApi $logger
     * @param OrderApi $orderApi
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        LogApi $logger,
        OrderApi $orderApi,
        ManagerInterface $messageManager
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
