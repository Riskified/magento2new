<?php
declare(strict_types=1);

namespace Riskified\Decider\Model\Cron;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Observer\UpdateOrderState;

class ReleaseOnHoldOrders
{
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaInterface $searchCriteria;
    private DecisionRepositoryInterface $decisionRepository;
    private Config $config;
    private UpdateOrderState $updateOrderStateObserver;
    private Log $log;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaInterface $searchCriteria,
        DecisionRepositoryInterface $decisionRepository,
        UpdateOrderState $updateOrderStateObserver,
        Config $config,
        Log $log
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteria = $searchCriteria;
        $this->decisionRepository = $decisionRepository;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->config = $config;
        $this->log = $log;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $searchCriteria = $this->searchCriteria->addFilter('status', '1')->setSortOrders()->create();
        $orderList = $this->orderRepository->getList($searchCriteria);

        if ($orderList->getTotalCount() == 0) {
            return;
        }

        $this->log("ReleaseOnHoldOrders: Found {$orderList->getTotalCount()} in hold state.");

        foreach ($orderList->getItems() as $order) {
            $this->log("ReleaseOnHoldOrders: Checking #{$order->getIncrementId()}.");
            $decision = $this->decisionRepository->getByOrderId($order->getId());

            if ($decision && $decision->getOrderId()) {
                $this->log(
                    "ReleaseOnHoldOrders: Found decision {$decision->getDecision()} for order #{$order->getIncrementId()}.
                    Triggering update state object."
                );

                $observer = new Observer();
                $observer->setOrder($order);
                $observer->setStatus($decision->getDecision());

                $this->updateOrderStateObserver->execute($observer);
            } else {
                $this->log("ReleaseOnHoldOrders: Decision for order #{$order->getIncrementId()} was not found.");
            }
        }
    }

    private function log(string $message) : void
    {
        if ($this->config->isLoggingEnabled()) {
            $this->log($message);
        }
    }
}
