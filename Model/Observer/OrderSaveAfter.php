<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Registry;
use Magento\Framework\Event\Observer;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Logger\Order as OrderLogger;

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

            if ($oldState == Order::STATE_HOLDED and $newState == Order::STATE_PROCESSING) {
                $this->_logger->debug(__("Order : " . $order->getId() . " not notifying on unhold action"));
                return;
            }

            $this->_logger->debug(__("Order: " . $order->getId() . " state changed from: " . $oldState . " to: " . $newState));

            // if we posted we should not re post
            if ($this->_registry->registry("riskified-order")) {
                $this->_logger->debug(__("Order : " . $order->getId() . " is already riskifiedInSave"));
                return;
            }

            try {
                if (!$this->_registry->registry("riskified-order")) {
                    $this->_registry->register("riskified-order", $order);
                }
                $this->_orderApi->post($order, Api::ACTION_UPDATE);

                $this->_registry->unregister("riskified-order");
            } catch (\Exception $e) {
                // There is no need to do anything here. The exception has already been handled and a retry scheduled.
                // We catch this exception so that the order is still saved in Magento.
            }
        } else {
            $this->_logger->debug(
                sprintf(
                    __("Order: %s state didn't change on save - not posting again: %s"),
                    $order->getIncrementId(),
                    $newState
                )
            );
        }
    }
}
