<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\Decider\Api\Api;

class OrderSaveAfter implements ObserverInterface
{
    private $_logger;
    private $_orderApi;

    public function __construct(
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Order $orderApi
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
        	
//         if ($order->dataHasChangedFor('state') && $order->getState() == 'processing') {
            if($order->getPayment()->getMethod() == 'authorizenet_directpost') {
                try {
                    $this->_orderApi->post($order, Api::ACTION_UPDATE);
                } catch (\Exception $e) {
                    $this->_logger->critical($e);
                }
            }
//         } else {
        
//             $this->_logger->debug(__("No data found"));
//         }
    }
}
