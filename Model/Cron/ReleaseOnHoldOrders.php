<?php
declare(strict_types=1);

namespace Riskified\Decider\Model\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Observer\UpdateOrderState;

class ReleaseOnHoldOrders
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteria;
    /**
     * @var DecisionRepositoryInterface
     */
    private DecisionRepositoryInterface $decisionRepository;
    /**
     * @var FilterBuilder
     */
    private FilterBuilder $filterBuilder;
    /**
     * @var Config
     */
    private Config $config;
    /**
     * @var UpdateOrderState
     */
    private UpdateOrderState $updateOrderStateObserver;
    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;
    /**
     * @var Log
     */
    private Log $log;
    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     *
     */
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
        Registry $registry
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteria = $searchCriteria;
        $this->filterBuilder = $filterBuilder;
        $this->decisionRepository = $decisionRepository;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->config = $config;
        $this->cache = $cache;
        $this->log = $log;
        $this->registry = $registry;
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

        $orders = $orderList->getItems();
        $maxAttemptsCount = $this->config->getCronMaxAttemptsCount();
        $failedOrders = [];

        $this->registry->register("riskified-order", array_values($orders)[0], true);

        $this->log("ReleaseOnHoldOrders: Found {$orderList->getTotalCount()} in hold state.");

        foreach ($orders as $order) {
            try {
                $this->log("ReleaseOnHoldOrders: Checking #{$order->getIncrementId()}.");
                $decision = $this->decisionRepository->getByOrderId((int)$order->getId());

                if ($decision && $decision->getOrderId()) {
                    if ($maxAttemptsCount <= $decision->getAttemptsCount()) {
                        $this->log("There's a problem with updating order {$order->getIncrementId()}. Reached too many attempts.");
                        $failedOrders[] = $order->getIncrementId();
                    }

                    $this->log(
                        "ReleaseOnHoldOrders: Found decision {$decision->getDecision()} for order #{$order->getIncrementId()}.
                        Triggering update state object."
                    );

                    $observer = new Observer();
                    $observer->setOrder($order);
                    $observer->setStatus($decision->getDecision());
                    $observer->setDescription($decision->getDescription());

                    $this->updateOrderStateObserver->execute($observer);
                } else {
                    $decision->setAttemptsCount($decision->getAttemptsCount() + 1);
                    $this->decisionRepository->save($decision);
                    $failedOrders[] = $order->getIncrementId();
                    $this->log("ReleaseOnHoldOrders: Decision for order #{$order->getIncrementId()} was not found.");
                }
            } catch (\Exception $e) {
                $failedOrders[] = $order->getIncrementId();
                $decision->setAttemptsCount($decision->getAttemptsCount() + 1);
                $this->decisionRepository->save($decision);

                $this->log("ReleaseOnHoldOrders: Decision for order #{$order->getIncrementId()} cannot be processed.");
            }
        }

        $this->cache->remove(self::CACHE_KEY);

        // delete old entries
        foreach ($this->decisionRepository->getOldDecisionEntries() as $decisionEntry) {
            $this->decisionRepository->getById($decisionEntry->getId());
        }
    }

    private function log(string $message) : void
    {
        if ($this->config->isLoggingEnabled()) {
            $this->log->log($message);
        }
    }
}
