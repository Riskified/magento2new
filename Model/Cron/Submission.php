<?php

namespace Riskified\Decider\Model\Cron;

use Riskified\Decider\Model\Queue;
use Riskified\Decider\Model\Api\Order as ApiOrder;
use Riskified\Decider\Model\Api\Config as ApiConfig;
use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class Submission
{
    const MAX_ATTEMPTS = 7;
    const INTERVAL_BASE = 3;
    const BATCH_SIZE = 10;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var OrderLogger
     */
    protected $logger;

    /**
     * @var ApiOrder
     */
    protected $api;

    /**
     * @var ApiConfig
     */
    protected $config;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var CollectionFactory
     */
    protected $orderFactory;

    /**
     * Submission constructor.
     *
     * @param Queue $queue
     * @param ApiOrder $api
     * @param ApiConfig $apiConfig
     * @param OrderLogger $logger
     * @param DateTime $date
     * @param CollectionFactory $orderFactory
     */
    public function __construct(
        Queue $queue,
        ApiOrder $api,
        ApiConfig $apiConfig,
        OrderLogger $logger,
        DateTime $date,
        CollectionFactory $orderFactory
    ) {
        $this->queue = $queue;
        $this->api = $api;
        $this->logger = $logger;
        $this->date = $date;
        $this->config = $apiConfig;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->logger->addInfo("Retrying failed order submissions");

        $retries = $this->queue->getCollection()
            ->addfieldtofilter(
                'attempts',
                array(
                    array('lt' => self::MAX_ATTEMPTS)
                )
            );

        $select = $retries->getSelect();
        $adapter = $select->getAdapter();
        $select
            ->where(sprintf(
                "TIMESTAMPDIFF(MINUTE, `updated_at`, %s) - POW(%s, attempts) > 0",
                $adapter->quote($this->date->gmtDate()),
                $adapter->quote(self::INTERVAL_BASE)
            ))
            ->order('updated_at ASC')
            ->limit(self::BATCH_SIZE);

        $mapperOrder = array();
        $orderIds = array();

        foreach ($retries as $retry) {
            $orderIds[] = $retry->getOrderId();
            $mapperOrder[$retry->getOrderId()] = $retry;
        }
        $collection = $this->orderFactory->create()->addFieldToFilter('entity_id', array('in' => $orderIds));

        foreach ($collection as $order) {
            $this->logger->addInfo("Retrying order " . $order->getId());

            try {
                $this->api->post($order, $mapperOrder[$order->getId()]->getAction());
            } catch (\Exception $e) {
                $this->logger->addCritical($e->getMessage());

                $mapperOrder[$order->getId()]
                    ->setLastError("Exception Message: " . $e->getMessage())
                    ->setAttempts($mapperOrder[$order->getId()]->getAttempts() + 1)
                    ->setUpdatedAt($this->date->gmtDate())
                    ->save();
            }
        }

        $this->logger->addInfo("Done retrying failed order submissions");
    }
}
