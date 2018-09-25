<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class SalesOrderAddressUpdate implements ObserverInterface
{
    private $logger;
    private $apiOrder;
    private $orderRepository;

    public function __construct(
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Order $orderApi,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->apiOrder = $orderApi;
        $this->orderRepository = $orderRepository;
    }

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
