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
use Riskified\Decider\Model\Observer\AutoInvoice;
use Riskified\Decider\Model\Observer\UpdateOrderState;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReleasePendingPaymentOrders
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
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteria,
        UpdateOrderState $updateOrderStateObserver,
        AutoInvoice $autoInvoiceObserver,
        Registry $registry
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteria = $searchCriteria;
        $this->filterBuilder = $filterBuilder;
        $this->decisionRepository = $decisionRepository;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->config = $config;
        $this->cache = $cache;
        $this->registry = $registry;
        $this->autoInvoiceObserver = $autoInvoiceObserver;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $orderStatusFilter = $this->filterBuilder
            ->setField('state')
            ->setValue('pending_payment')
            ->setConditionType('eq')
            ->create();

        $searchCriteria = $this->searchCriteria->addFilter($orderStatusFilter)->create();
        $orderList = $this->orderRepository->getList($searchCriteria);

        if ($orderList->getTotalCount() == 0) {
            return;
        }

        $orders = $orderList->getItems();
        $this->registry->register("riskified-order", array_values($orders)[0], true);
        $toRemove = [];

        foreach ($orders as $order) {
            try {
                $decision = $this->decisionRepository->getByOrderId((int)$order->getId());

                if ($decision && $decision->getOrderId()) {
                    $observer = new Observer();
                    $observer->setOrder($order);
                    $observer->setStatus($decision->getDecision());
                    $observer->setDescription($decision->getDescription());

                    $this->updateOrderStateObserver->execute($observer);

                    if ($decision->getDecision() == "approved" && $this->config->isAutoInvoiceEnabled()) {
                        $this->autoInvoiceObserver->execute($observer);
                    }
                    $toRemove[] = $decision;
                }
            } catch (\Exception $e) {
            }
        }

        // delete old entries
        foreach ($toRemove as $decisionEntry) {
            $this->decisionRepository->deleteById((int)$decisionEntry->getId());
        }
    }

}
