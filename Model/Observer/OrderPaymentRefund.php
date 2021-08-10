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


    private $registry;

    /**
     * OrderPaymentRefund constructor.
     *
     * @param LogApi $logger
     * @param OrderApi $orderApi
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        LogApi $logger,
        OrderApi $orderApi,
        ManagerInterface $messageManager
    ) {
        $this->registry = $registry;
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
            $creditMemo = $observer->getEvent()->getCreditmemo();
            $order = $creditMemo->getOrder();
            $this->saveMemoInRegistry($creditMemo);
            $this->apiOrderLayer->post($order, Api::ACTION_REFUND);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __("Riskified API Respond : %1", $e->getMessage())
            );
            $this->logger->logException($e);
        }
    }

    /**
     * @param $creditMemo
     */
    public function saveMemoInRegistry($creditMemo)
    {
        $this->registry->register('creditMemo', $creditMemo);
    }
}
