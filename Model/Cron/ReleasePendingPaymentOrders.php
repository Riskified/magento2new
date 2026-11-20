<?php

declare(strict_types=1);

namespace Riskified\Decider\Model\Cron;

use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Observer\AutoInvoice;
use Riskified\Decider\Model\Observer\UpdateOrderState;

class ReleasePendingPaymentOrders
{
    private OrderCollectionFactory $orderCollectionFactory;
    private StoreManagerInterface $storeManager;
    private DecisionRepositoryInterface $decisionRepository;
    private Config $config;
    private UpdateOrderState $updateOrderStateObserver;
    private AutoInvoice $autoInvoiceObserver;
    private Registry $registry;
    private Log $log;

    public function __construct(
        Config $config,
        DecisionRepositoryInterface $decisionRepository,
        OrderCollectionFactory $orderCollectionFactory,
        StoreManagerInterface $storeManager,
        UpdateOrderState $updateOrderStateObserver,
        AutoInvoice $autoInvoiceObserver,
        Registry $registry,
        Log $log
    ) {
        $this->config = $config;
        $this->decisionRepository = $decisionRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->storeManager = $storeManager;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->autoInvoiceObserver = $autoInvoiceObserver;
        $this->registry = $registry;
        $this->log = $log;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->isEnabled($store->getId())) {
                $storeIds[] = $store->getId();
            }
        }

        if (!$storeIds) {
            return;
        }

        // Only orders with a Riskified decision that hasn't failed too often: pending-payment orders
        // without one (e.g. abandoned 3DS) or that keep failing would otherwise fill every batch.
        $orders = $this->orderCollectionFactory->create();
        $orders->addFieldToFilter('main_table.state', 'pending_payment')
            ->addFieldToFilter('main_table.store_id', ['in' => $storeIds]);
        $orders->getSelect()
            ->where(
                'EXISTS (SELECT 1 FROM ' . $orders->getTable('riskified_decision_queue')
                . ' AS d WHERE d.order_id = main_table.entity_id AND d.attempts_count < ?)',
                $this->config->getCronMaxAttemptsCount()
            )
            ->order('main_table.entity_id ASC')
            ->limit($this->config->getPendingPaymentBatchSize());

        if (!$orders->count()) {
            return;
        }

        $toRemove = [];

        foreach ($orders as $order) {
            $decision = null;
            // Batch spans stores: point store-scoped config and registry at this order.
            $this->config->setStore($order->getStoreId());
            $this->registry->register("riskified-order", $order, true);
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
                if ($decision && $decision->getId()) {
                    $decision->setAttemptsCount($decision->getAttemptsCount() + 1);
                    $this->decisionRepository->save($decision);
                }
                if ($this->config->isLoggingEnabled()) {
                    $this->log->log("Pending payment release failed for order #{$order->getIncrementId()}: " . $e->getMessage());
                }
            }
        }

        // delete old entries
        foreach ($toRemove as $decisionEntry) {
            $this->decisionRepository->deleteById((int)$decisionEntry->getId());
        }
    }
}
