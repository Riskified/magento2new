<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Log as LogApi;
use Riskified\Decider\Model\Api\Order as ApiOrder;

class OrderPaymentCancel implements ObserverInterface
{
    /**
     * @var LogApi
     */
    private $logger;

    /**
     * @var ApiOrder
     */
    private $apiOrderLayer;

    /**
     * OrderPaymentCancel constructor.
     *
     * @param LogApi $logger
     * @param ApiOrder $orderApi
     */
    public function __construct(
        LogApi $logger,
        ApiOrder $orderApi
    ) {
        $this->logger = $logger;
        $this->apiOrderLayer = $orderApi;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
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
