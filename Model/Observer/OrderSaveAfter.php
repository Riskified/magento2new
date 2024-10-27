<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Registry;
use Magento\Framework\Event\Observer;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Api\Order\Log as OrderLogger;

class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var OrderLogger
     */
    private $_logger;

    /**
     * @var OrderApi
     */
    private $_orderApi;
    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * OrderSaveAfter constructor.
     *
     * @param OrderLogger $logger
     * @param OrderApi $orderApi
     * @param  $registry
     */
    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi,
        Registry $registry
    ) {
        $this->_logger = $logger;
        $this->_orderApi = $orderApi;
        $this->_registry = $registry;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();

        if (!$order) {
            return;
        }

        $newState = $order->getState();

        if ((int)$order->dataHasChangedFor('state') === 1) {
            $oldState = $order->getOrigData('state');

            // processing order for worldpay
            if ($oldState == 'new' && $newState == Order::STATE_PROCESSING) {
                if ($order->getPayment()->getMethod() == "worldpay_cc") {
                    $this->_logger->log("Order #{$order->getIncrementId()} changed status from pending to processing and paid with worldpay. Attempting to send to Riskified.");
                    $this->_registry->unregister("riskified-order");
                    $this->submitOrder($order);
                }
            }

            // processing order for checkoutcom
            if ($oldState == 'new' && $newState == Order::STATE_PROCESSING) {
                if ($order->getPayment()->getMethod() == "checkoutcom_card_payment" && !empty($order->getPayment()->getLastTransId())) {
                    $this->_logger->log("Order #{$order->getIncrementId()} changed status from pending to processing and paid with checkoutcom. Attempting to send to Riskified.");
                    $this->_registry->unregister("riskified-order");
                    $this->submitOrder($order);
                }
            }

            if ($newState == Order::STATE_PROCESSING && $order->getPayment()->getMethod() == "adyen_paypal") {
                $this->_logger->log(
                    "Order #{$order->getIncrementId()} changed status to processing but it was paid with paypal. Preventing second call to Riskified api"
                );
                return;
            }

            if ($newState != "adyen_authorized") {
                if ($oldState != Order::STATE_PAYMENT_REVIEW || $newState != Order::STATE_PROCESSING) {
                    return;
                }

                if ($oldState == Order::STATE_HOLDED and $newState == Order::STATE_PROCESSING) {
                    $this->_logger->log(__("Order #" . $order->getIncrementId() . " not notifying on unhold action"));
                    return;
                }
            }

            $this->_logger->log(__("Order #" . $order->getIncrementId() . " state changed from: " . $oldState . " to: " . $newState));
            $this->submitOrder($order);
        } else {
            $this->_logger->log(
                sprintf(
                    __("Order #%s state didn't change on save - not posting again: %s"),
                    $order->getIncrementId(),
                    $newState
                )
            );
        }
    }

    private function submitOrder($order)
    {
        // if we posted we should not re post
        if ($this->_registry->registry("riskified-order")) {
            $this->_logger->log(__("Order #" . $order->getIncrementId() . " is already riskifiedInSave"));
            return;
        }

        try {
            if (!$this->_registry->registry("riskified-order")) {
                $this->_registry->register("riskified-order", $order, true);
            }
            $this->_logger->log(__("Order #" . $order->getIncrementId() . " processing action update from riskified."));
            $this->_orderApi->post($order, Api::ACTION_UPDATE);

            $this->_registry->unregister("riskified-order");
        } catch (\Exception $e) {
            // There is no need to do anything here. The exception has already been handled and a retry scheduled.
            // We catch this exception so that the order is still saved in Magento.
        }
    }
}
