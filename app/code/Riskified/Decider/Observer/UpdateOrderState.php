<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use \Magento\Sales\Model\Order;

class UpdateOrderState implements ObserverInterface {
    private $logger;
    private $apiOrderLayer;
    private $apiConfig;
    private $apiOrderConfig;

    /**
     * UpdateOrderState constructor.
     *
     * @param \Riskified\Decider\Api\Log $logger
     * @param \Riskified\Decider\Api\Config $config
     * @param \Riskified\Decider\Api\Order\Config $apiOrderConfig
     * @param \Riskified\Decider\Api\Order $orderApi
     */
    public function __construct(
        \Riskified\Decider\Api\Log $logger,
        \Riskified\Decider\Api\Config $config,
        \Riskified\Decider\Api\Order\Config $apiOrderConfig,
        \Riskified\Decider\Api\Order $orderApi
    ) {
        $this->logger         = $logger;
        $this->apiOrderConfig = $apiOrderConfig;
        $this->apiOrderLayer  = $orderApi;
        $this->apiConfig      = $config;
    }

    /**
     * Observer handler
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute( \Magento\Framework\Event\Observer $observer ) {
        $order              = $observer->getOrder();
        $riskifiedStatus    = (string) $observer->getStatus();
        $riskifiedOldStatus = (string) $observer->getOldStatus();
        $description        = (string) $observer->getDescription();
        $newState           = $newStatus = null;
        $currentState       = $order->getState();
        $currentStatus      = $order->getStatus();

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

        switch ( $riskifiedStatus ) {
            case 'approved':
                if ( $currentState == Order::STATE_HOLDED
                     && ( $currentStatus == $this->apiOrderConfig->getOnHoldStatusCode()
                          || $currentStatus == $this->apiOrderConfig->getTransportErrorStatusCode() )
                ) {
                    $newState  = $this->apiOrderConfig->getSelectedApprovedState();
                    $newStatus = $this->apiOrderConfig->getSelectedApprovedStatus();
                }
                break;
            case 'declined':
                if ( $currentState == Order::STATE_HOLDED
                     && ( $currentStatus == $this->apiOrderConfig->getOnHoldStatusCode()
                          || $currentStatus == $this->apiOrderConfig->getTransportErrorStatusCode() )
                ) {
                    $newState  = $this->apiOrderConfig->getSelectedDeclinedState();
                    $newStatus = $this->apiOrderConfig->getSelectedDeclinedStatus();
                }
                break;
            case 'submitted':
                if ( $currentState == Order::STATE_PROCESSING
                     || ( $currentState == Order::STATE_HOLDED
                          && $currentStatus == $this->apiOrderConfig->getTransportErrorStatusCode() )
                ) {
                    $newState  = Order::STATE_HOLDED;
                    $newStatus = $this->apiOrderConfig->getOnHoldStatusCode();
                }
                break;
            case 'error':
                if ( $currentState == Order::STATE_PROCESSING
                     && $this->apiConfig->isAutoInvoiceEnabled()
                ) {
                    $newState  = Order::STATE_HOLDED;
                    $newStatus = $this->apiOrderConfig->getTransportErrorStatusCode();
                }
        }

        $changed = false;


        if ( $newState
             && ( $newState != $currentState || $newStatus != $currentStatus )
             && $this->apiConfig->getConfigStatusControlActive()
        ) {
            $this->saveStatusBeforeHold($newState, $order);

            if ( $newState == Order::STATE_CANCELED ) {
                $this->logger->log( "Order '" . $order->getId() . "' should be canceled - calling cancel method" );
                $order->cancel();
                $order->addStatusHistoryComment($description, $newStatus);
            } else {
                $order->setState( $newState, $newStatus, $description );
                $order->setStatus( $newStatus );
                $order->addStatusHistoryComment( $description, $newStatus );

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
        } elseif ( $description && $riskifiedStatus != $riskifiedOldStatus ) {
            if ( $riskifiedStatus != 'approved' || ! $this->apiConfig->isAutoInvoiceEnabled() ) {
                $this->logger->log(
                    sprintf(
                    "Updated order %s history comment to: %s",
                        $order->getId(),
                        $description
                    )
                );
                $order->addStatusHistoryComment( $description );
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

        if ( $changed ) {
            try {
                $order->save();
            } catch ( \Exception $e ) {
                $this->logger->log( "Error saving order: " . $e->getMessage() );

                return;
            }
        }
    }

    private function saveStatusBeforeHold($newState, $order)
    {
        if ($newState == Order::STATE_HOLDED) {
            $order->setHoldBeforeState($order->getState());
            $order->setHoldBeforeStatus($order->getStatus());
        }

        return $this;
    }
}
