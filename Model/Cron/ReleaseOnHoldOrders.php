<?php
declare(strict_types=1);

namespace Riskified\Decider\Model\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Observer\UpdateOrderState;

class ReleaseOnHoldOrders
{
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaBuilder $searchCriteria;
    private DecisionRepositoryInterface $decisionRepository;
    private FilterBuilder $filterBuilder;
    private Config $config;
    private UpdateOrderState $updateOrderStateObserver;
    private CacheInterface $cache;
    private Log $log;

    const CACHE_KEY = "prevent_overlapping_cron";

    public function __construct(
        CacheInterface $cache,
        Config $config,
        DecisionRepositoryInterface $decisionRepository,
        FilterBuilder $filterBuilder,
        Log $log,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteria,
        UpdateOrderState $updateOrderStateObserver,
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteria = $searchCriteria;
        $this->filterBuilder = $filterBuilder;
        $this->decisionRepository = $decisionRepository;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->config = $config;
        $this->cache = $cache;
        $this->log = $log;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        if ($this->cache->load(self::CACHE_KEY)) {
            return;
        }

        $this->cache->save(1, self::CACHE_KEY, [], 15);

        $orderStatusFilter = $this->filterBuilder
            ->setField('state')
            ->setValue('holded')
            ->setConditionType('eq')
            ->create();

        $searchCriteria = $this->searchCriteria->addFilter($orderStatusFilter)->create();
        $orderList = $this->orderRepository->getList($searchCriteria);

        if ($orderList->getTotalCount() == 0) {
            return;
        }

        $this->log("ReleaseOnHoldOrders: Found {$orderList->getTotalCount()} in hold state.");

        foreach ($orderList->getItems() as $order) {
            try {
                $this->log("ReleaseOnHoldOrders: Checking #{$order->getIncrementId()}.");
                $decision = $this->decisionRepository->getByOrderId((int)$order->getId());

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
            } catch (\Exception $e) {
                $this->log("ReleaseOnHoldOrders: Decision for order #{$order->getIncrementId()} cannot be processed.");
            }
        }
    }

    private function log(string $message) : void
    {
        if ($this->config->isLoggingEnabled()) {
            $this->log->log($message);
        }
    }
}
