<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class SalesOrderAddressUpdate implements ObserverInterface
{
    /**
     * @var \Riskified\Decider\Logger\Order
     */
    private $logger;

    /**
     * @var \Riskified\Decider\Api\Order
     */
    private $apiOrder;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * SalesOrderAddressUpdate constructor.
     *
     * @param \Riskified\Decider\Logger\Order $logger
     * @param \Riskified\Decider\Api\Order $orderApi
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Order $orderApi,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
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
