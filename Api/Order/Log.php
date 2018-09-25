<?php
namespace Riskified\Decider\Api\Order;

class Log
{
    private $_logger;

    public function __construct(\Riskified\Decider\Logger\Order $logger)
    {
        $this->_logger = $logger;
    }

    public function payment($model)
    {
        $this->_logger->addInfo("Payment info debug Logs:");
        try {
            $payment = $model->getPayment();
            $gateway_name = $payment->getMethod();
            $this->_logger->addInfo("Payment Gateway: " . $gateway_name);
            $this->_logger->addInfo("payment->getCcLast4(): " . $payment->getCcLast4());
            $this->_logger->addInfo("payment->getCcType(): " . $payment->getCcType());
            $this->_logger->addInfo("payment->getCcCidStatus(): " . $payment->getCcCidStatus());
            $this->_logger->addInfo("payment->getCcAvsStatus(): " . $payment->getCcAvsStatus());
            $this->_logger->addInfo("payment->getAdditionalInformation(): " . PHP_EOL . var_export($payment->getAdditionalInformation(), 1));
            $sage = $model->getSagepayInfo();

            if (is_object($sage)) {
                $this->_logger->addInfo("sagepay->getLastFourDigits(): " . $sage->getLastFourDigits());
                $this->_logger->addInfo("sagepay->last_four_digits: " . $sage->getData('last_four_digits'));
                $this->_logger->addInfo("sagepay->getCardType(): " . $sage->getCardType());
                $this->_logger->addInfo("sagepay->card_type: " . $sage->getData('card_type'));
                $this->_logger->addInfo("sagepay->getAvsCv2Status: " . $sage->getAvsCv2Status());
                $this->_logger->addInfo("sagepay->address_result: " . $sage->getData('address_result'));
                $this->_logger->addInfo("sagepay->getCv2result: " . $sage->getCv2result());
                $this->_logger->addInfo("sagepay->cv2result: " . $sage->getData('cv2result'));
                $this->_logger->addInfo("sagepay->getAvscv2: " . $sage->getAvscv2());
                $this->_logger->addInfo("sagepay->getAddressResult: " . $sage->getAddressResult());
                $this->_logger->addInfo("sagepay->getPostcodeResult: " . $sage->getPostcodeResult());
                $this->_logger->addInfo("sagepay->getDeclineCode: " . $sage->getDeclineCode());
                $this->_logger->addInfo("sagepay->getBankAuthCode: " . $sage->getBankAuthCode());
                $this->_logger->addInfo("sagepay->getPayerStatus: " . $sage->getPayerStatus());
            }
            if ($gateway_name == "optimal_hosted") {
                $optimalTransaction = unserialize($payment->getAdditionalInformation('transaction'));
                if ($optimalTransaction) {
                    $this->_logger->addInfo("Optimal transaction: ");
                    $this->_logger->addInfo("transaction->cvdVerification: " . $optimalTransaction->cvdVerification);
                    $this->_logger->addInfo("transaction->houseNumberVerification: " . $optimalTransaction->houseNumberVerification);
                    $this->_logger->addInfo("transaction->zipVerification: " . $optimalTransaction->zipVerification);
                } else {
                    $this->_logger->addInfo("Optimal gateway but no transaction found");
                }
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }

    public function logInvoice($order)
    {
        try {
            $this->_logger->debug("Order " . $order->getId() . " parameters relevant to invoicing failure:");
            $this->_logger->debug("Order state: " . $order->getState());
            $this->_logger->debug("Order status: " . $order->getStatus());
            $this->_logger->debug("UNHOLD action flag: " . $order->getActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_UNHOLD));
            $this->_logger->debug("INVOICE action flag: " . $order->getActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_INVOICE));
            foreach ($order->getAllItems() as $item) {
                $this->_logger->debug("item " . $item->getProductId() . " - qty: " . $item->getQtyToInvoice() . "  locked: " . $item->getLockedDoInvoice());
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }

    public function log($message)
    {
        $this->_logger->addInfo($message);
    }

    public function logException($message)
    {
        $this->_logger->addCritical($message);
    }
}