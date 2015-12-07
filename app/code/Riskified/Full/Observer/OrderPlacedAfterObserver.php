<?php
namespace Riskified\Full\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Full\Api\Api;

class OrderPlacedAfterObserver implements ObserverInterface
{
    private $_logger;
    private $_orderApi;

    public function __construct(
        \Riskified\Full\Logger\Order $logger,
        \Riskified\Full\Api\Order $orderApi
    ){
        $this->_logger = $logger;
        $this->_orderApi = $orderApi;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();

        if(!$order) {
            return;
        }

        if ($order->dataHasChangedFor('state')) {
            try {
                $this->_orderApi->postOrder($order, Api::ACTION_UPDATE);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        } else {
            $this->_logger->debug(__("No data found"));
        }
    }
}
