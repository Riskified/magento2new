<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Riskified\Decider\Model\Api\Config as ApiConfig;
use Riskified\Decider\Model\Api\Log as LogApi;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Api\Order\Config as OrderConfig;

class UpdateOrderState implements ObserverInterface
{
    /**
     * @var LogApi
     */
    private $logger;

    /**
     * @var OrderApi
     */
    private $apiOrderLayer;

    /**
     * @var ApiConfig
     */
    private $apiConfig;

    /**
     * @var OrderConfig
     */
    private $apiOrderConfig;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $orderRepository;

    /**
     * UpdateOrderState constructor.
     *
     * @param LogApi $logger
     * @param ApiConfig $config
     * @param OrderConfig $apiOrderConfig
     * @param OrderApi $orderApi
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        LogApi $logger,
        ApiConfig $config,
        OrderConfig $apiOrderConfig,
        OrderApi $orderApi,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->apiOrderConfig = $apiOrderConfig;
        $this->apiOrderLayer = $orderApi;
        $this->apiConfig = $config;
        $this->resource = $resource;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Observer handler
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();
        $riskifiedStatus = (string)$observer->getStatus();
        $riskifiedOldStatus = (string)$observer->getOldStatus();
        $description = (string)$observer->getDescription();
        $newState = $newStatus = null;
        $currentState = $order->getState();
        $currentStatus = $order->getStatus();

        $this->logger->log(
            sprintf(
                "Checking if should update order '%s' from state: '%s' and status: '%s'",
                $order->getId(),
                $currentState,
                $currentStatus
            )
        );

        $this->logger->log(
            sprintf(
                "Data received from riskified: status: %s, old_status: %s, description: %s",
                $riskifiedStatus,
                $riskifiedOldStatus,
                $description
            )
        );

        $this->logger->log(
            sprintf(
                "On Hold Status Code : %s and Transport Error Status Code : %s",
                $this->apiOrderConfig->getOnHoldStatusCode(),
                $this->apiOrderConfig->getTransportErrorStatusCode()
            )
        );

        switch ($riskifiedStatus) {
            case 'approved':
                if ($currentState == Order::STATE_HOLDED
                    && ($currentStatus == $this->apiOrderConfig->getOnHoldStatusCode()
                        || $currentStatus == $this->apiOrderConfig->getTransportErrorStatusCode())
                ) {
                    $newState = $this->apiOrderConfig->getSelectedApprovedState();
                    $newStatus = $this->apiOrderConfig->getSelectedApprovedStatus();
                }
                break;
            case 'declined':
                if ($currentState == Order::STATE_HOLDED
                    && ($currentStatus == $this->apiOrderConfig->getOnHoldStatusCode()
                        || $currentStatus == $this->apiOrderConfig->getTransportErrorStatusCode())
                ) {
                    $newState = $this->apiOrderConfig->getSelectedDeclinedState();
                    $newStatus = $this->apiOrderConfig->getSelectedDeclinedStatus();
                }
                break;
            case 'submitted':
                if ($currentState == Order::STATE_PROCESSING
                    || ($currentState == Order::STATE_HOLDED
                        && $currentStatus == $this->apiOrderConfig->getTransportErrorStatusCode())
                ) {
                    $newState = Order::STATE_HOLDED;
                    $newStatus = $this->apiOrderConfig->getOnHoldStatusCode();
                }
                break;
            case 'error':
                if ($currentState == Order::STATE_PROCESSING
                    && $this->apiConfig->isAutoInvoiceEnabled()
                ) {
                    $newState = Order::STATE_HOLDED;
                    $newStatus = $this->apiOrderConfig->getTransportErrorStatusCode();
                }
        }
        $changed = false;

        if ($newState
            && ($newState != $currentState || $newStatus != $currentStatus)
            && $this->apiConfig->getConfigStatusControlActive()
        ) {
            $this->saveStatusBeforeHold($newState, $order);

            if ($newState == Order::STATE_CANCELED) {
                $this->logger->log(
                    sprintf(
                        "Order '%s' should be canceled - calling cancel method",
                        $order->getId()
                    )
                );

                $order->unhold();

                $order->addStatusHistoryComment(
                    __("Order was unholded manually")
                );

                $order->cancel();
                $order->addStatusHistoryComment($description, $newStatus);
            } else {
                $order->setState($newState, $newStatus, $description);
                $order->setStatus($newStatus);
                $order->addStatusHistoryComment($description, $newStatus);

                $this->logger->log(
                    sprintf(
                        "Updated order '%s' to: state:  '%s', status: '%s', description: '%s'",
                        $order->getId(),
                        $newState,
                        $newStatus,
                        $description
                    )
                );
            }

            $changed = true;
        } elseif ($description && $riskifiedStatus != $riskifiedOldStatus) {
            if ($riskifiedStatus != 'approved' || ! $this->apiConfig->isAutoInvoiceEnabled()) {
                $this->logger->log(
                    sprintf(
                        "Updated order %s history comment to: %s",
                        $order->getId(),
                        $description
                    )
                );
                $order->addStatusHistoryComment($description);
                $changed = true;
            }
        } else {
            $this->logger->log(
                sprintf(
                    "No update to state, status, comments is required for %s",
                    $order->getId()
                )
            );
        }

        if ($changed) {
            try {
                $this->logger->log("Changing order status #" . $order->getIncrementId());
                $this->orderRepository->save($order);
            } catch (\Exception $e) {
                $this->logger->log("Error saving order: " . $e->getMessage());

                return;
            }
        }
    }

    /**
     * @param $newState
     * @param $order
     *
     * @return $this
     */
    private function saveStatusBeforeHold($newState, $order)
    {
        if ($newState == Order::STATE_HOLDED) {
            if ($order->getState() != Order::STATE_HOLDED) {
                $order->setHoldBeforeState($order->getState());
                $order->setHoldBeforeStatus($order->getStatus());
            } else {
                $historyCollection = $order->getStatusHistoryCollection();
                $avoidStatuses = [
                    $this->apiOrderConfig->getSelectedApprovedStatus(),
                    $this->apiOrderConfig->getTransportErrorStatusCode(),
                    $this->apiOrderConfig->getSelectedDeclinedStatus(),
                    "holded",
                    "riskified_holded",
                    "riskified_approved",
                    "riskified_declined",
                    "riskified_approved",
                ];

                $status = false;
                foreach ($historyCollection as $historyRow) {
                    if (!in_array($historyRow->getStatus(), $avoidStatuses)) {
                        $status = $historyRow->getStatus();
                        break;
                    }
                }
                if ($status !== false) {
                    $connection = $this->resource->getConnection(
                        \Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION
                    );
                    $tableOrderStatuses = $connection->getTableName('sales_order_status_state');

                    $result = $connection->fetchRow(
                        'SELECT state FROM `' . $tableOrderStatuses . '` WHERE status = ?',
                        [$status]
                    );
                    $state = $result['state'];

                    $order->setHoldBeforeState($state);
                    $order->setHoldBeforeStatus($status);
                }
            }
        }

        return $this;
    }
}
