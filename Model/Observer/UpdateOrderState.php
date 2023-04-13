<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
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
     * @var Registry
     */
    private $registry;
    private $state;

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
        Context $context,
        OrderApi $orderApi,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\ResourceConnection $resource,
        Registry $registry
    ) {
        $this->logger = $logger;
        $this->apiOrderConfig = $apiOrderConfig;
        $this->apiOrderLayer = $orderApi;
        $this->apiConfig = $config;
        $this->state = $context->getAppState();
        $this->resource = $resource;
        $this->orderRepository = $orderRepository;
        $this->registry = $registry;
    }

    /**
     * Observer handler
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var OrderInterface $order */
        $order = $observer->getOrder();
        $riskifiedStatus = (string)$observer->getStatus();
        $riskifiedOldStatus = (string)$observer->getOldStatus();
        $description = (string)$observer->getDescription();
        $newState = $newStatus = null;
        $currentState = $order->getState();
        $currentStatus = $order->getStatus();
        $preventSavingStatuses = false;

        if ($order->getPayment()->getMethod() == "flxpayment") {
            $preventSavingStatuses = true;
        }

        $this->logger->log(
            sprintf(
                "Checking if should update order '%s' (#%s) from state: '%s' and status: '%s'",
                $order->getId(),
                $order->getIncrementId(),
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

        if ($this->apiConfig->isLoggingEnabled()) {
            $this->logger->log(
                sprintf(
                    "On Hold Status Code : %s and Transport Error Status Code : %s",
                    $this->apiOrderConfig->getOnHoldStatusCode(),
                    $this->apiOrderConfig->getTransportErrorStatusCode()
                )
            );
        }
        
        switch ($riskifiedStatus) {
            case 'approved':
                if (($currentState == Order::STATE_HOLDED
                        || $currentState == Order::STATE_PAYMENT_REVIEW
                        || $currentState == Order::STATE_PENDING_PAYMENT)
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
                    || $currentState == Order::STATE_PENDING_PAYMENT
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

        if ($preventSavingStatuses) {
            if ($this->apiConfig->isLoggingEnabled()) {
                $this->logger->log(
                    "Order #{$order->getIncrementId()} prevented saving order. Saving comment '$description'"
                );
            }
            $placeOrderAfter = $this->registry->registry("riskified-place-order-after");
            $order->addCommentToStatusHistory($description);

            try {
                if (!$placeOrderAfter) {
                    $this->orderRepository->save($order);
                }
            } catch (\Exception $e) {
                if ($this->apiConfig->isLoggingEnabled()) {
                    $this->logger->log("Error saving order #{$order->getIncrementId()}: " . $e->getMessage());
                }
            }

            return;
        } else if ($newState
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

                $order->addCommentToStatusHistory(
                    __("Order was unhold manually")
                );

                if (!$order->canCancel()) {
                    $this->logger->log("Order #{$order->getIncrementId()} cannot be cancelled.");
                }

                $this->state->emulateAreaCode(
                    'adminhtml',
                    [$order, 'cancel']
                );

                $order->addCommentToStatusHistory($description, $newStatus);
            } else {
                $order->setState($newState);
                $order->setStatus($newStatus);
                $order->addCommentToStatusHistory($description, $newStatus);

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
                $order->addCommentToStatusHistory($description);
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
                $this->registry->register("riskified-order", $order, true);
                $placeOrderAfter = $this->registry->registry("riskified-place-order-after");

                if (!$this->apiConfig->isAutoInvoiceEnabled() && !$placeOrderAfter) {
                    $this->orderRepository->save($order);
                } else if ($newState != "processing") {
                    $this->orderRepository->save($order);
                }
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
                    "riskified_holded",
                    "riskified_approved",
                    "riskified_declined",
                    "riskified_approved",
                    Order::STATE_HOLDED,
                    Order::STATE_PENDING_PAYMENT
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
                    $result = $connection->fetchRow('SELECT state FROM `' . $tableOrderStatuses . '` WHERE status="' . $status . '"');
                    $state = $result['state'];

                    $order->setHoldBeforeState($state);
                    $order->setHoldBeforeStatus($status);
                }
            }
        }

        return $this;
    }
}
