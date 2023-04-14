<?php
declare(strict_types=1);

namespace Riskified\Decider\Model\Command;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Riskified\Decider\Model\Observer\AutoInvoice;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Observer\UpdateOrderState;

class ReleaseOnHoldOrders extends Command
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
     * @var Registry
     */
    private Registry $registry;

    /** @var \Magento\Framework\App\State **/
    private $state;
    private $autoInvoiceObserver;

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
        Registry $registry,
        \Magento\Framework\App\State $state
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteria = $searchCriteria;
        $this->filterBuilder = $filterBuilder;
        $this->decisionRepository = $decisionRepository;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->config = $config;
        $this->cache = $cache;
        $this->registry = $registry;
        $this->state = $state;
        $this->autoInvoiceObserver = $autoInvoiceObserver;


        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('riskified:release-on-hold')
            ->setDescription('Release manually holded orders.');

        parent::configure();
    }

    /**
     * Execute.
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

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
        $maxAttemptsCount = $this->config->getCronMaxAttemptsCount();
        $failedOrders = [];

        $this->registry->register("riskified-order", array_values($orders)[0], true);

        $output->writeln("Found {$orderList->getTotalCount()} in hold state.");

        foreach ($orders as $order) {
            try {
                $output->writeln("Checking #{$order->getIncrementId()}.");
                $decision = $this->decisionRepository->getByOrderId((int)$order->getId());

                if ($decision && $decision->getOrderId()) {
                    if ($maxAttemptsCount <= $decision->getAttemptsCount()) {
                        $output->writeln("There's a problem with updating order {$order->getIncrementId()}. Reached too many attempts.");
                        $failedOrders[] = $order->getIncrementId();
                    }

                    $output->writeln(
                        "Found decision {$decision->getDecision()} for order #{$order->getIncrementId()}. Triggering update state object."
                    );

                    $observer = new Observer();
                    $observer->setOrder($order);
                    $observer->setStatus($decision->getDecision());
                    $observer->setDescription($decision->getDescription());

                    $this->updateOrderStateObserver->execute($observer);
                    $this->autoInvoiceObserver->execute($observer);
                }
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
                $output->writeln("Decision for order #{$order->getIncrementId()} cannot be processed.");
            }
        }

        $this->cache->remove(self::CACHE_KEY);

        // delete old entries
        foreach ($this->decisionRepository->getOldDecisionEntries() as $decisionEntry) {
            $this->decisionRepository->deleteById((int)$decisionEntry->getId());
        }
    }
}
