<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Magento\Sales\Api\OrderRepositoryInterface;

class SalesOrderAddressUpdate implements ObserverInterface
{
    /**
     * @var OrderLogger
     */
    private $logger;

    /**
     * @var OrderApi
     */
    private $apiOrder;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * SalesOrderAddressUpdate constructor.
     *
     * @param OrderLogger $logger
     * @param OrderApi $orderApi
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->apiOrder = $orderApi;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order_id = $observer->getOrderId();
            $order = $this->orderRepository->get($order_id);

            if (!$order) {
                return;
            }

            $this->apiOrder->post($order, Api::ACTION_UPDATE);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
