<?php

namespace Riskified\Decider\Model\Api\Order;

use Riskified\Decider\Model\Logger\Order as OrderLogger;
use Magento\Sales\Model\Order;

class Log
{
    /**
     * @var OrderLogger
     */
    private $_logger;

    /**
     * Log constructor.
     *
     * @param OrderLogger $logger
     */
    public function __construct(OrderLogger $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * @param $model
     */
    public function payment($model)
    {
        $this->_logger->info("Payment info debug Logs:");
        try {
            $payment = $model->getPayment();
            $gateway_name = $payment->getMethod();
            $this->_logger->info("#{$model->getIncrementId()} Payment Gateway: " . $gateway_name);
            $this->_logger->info("#{$model->getIncrementId()} payment->getCcLast4(): " . $payment->getCcLast4());
            $this->_logger->info("#{$model->getIncrementId()} payment->getCcType(): " . $payment->getCcType());
            $this->_logger->info("#{$model->getIncrementId()} payment->getCcCidStatus(): " . $payment->getCcCidStatus());
            $this->_logger->info("#{$model->getIncrementId()} payment->getCcAvsStatus(): " . $payment->getCcAvsStatus());
            $this->_logger->info("#{$model->getIncrementId()} payment->getAdditionalInformation(): " . PHP_EOL . json_encode($payment->getAdditionalInformation()));
            $sage = $model->getSagepayInfo();

            if (is_object($sage)) {
                $this->_logger->info("sagepay->getLastFourDigits(): " . $sage->getLastFourDigits());
                $this->_logger->info("sagepay->last_four_digits: " . $sage->getData('last_four_digits'));
                $this->_logger->info("sagepay->getCardType(): " . $sage->getCardType());
                $this->_logger->info("sagepay->card_type: " . $sage->getData('card_type'));
                $this->_logger->info("sagepay->getAvsCv2Status: " . $sage->getAvsCv2Status());
                $this->_logger->info("sagepay->address_result: " . $sage->getData('address_result'));
                $this->_logger->info("sagepay->getCv2result: " . $sage->getCv2result());
                $this->_logger->info("sagepay->cv2result: " . $sage->getData('cv2result'));
                $this->_logger->info("sagepay->getAvscv2: " . $sage->getAvscv2());
                $this->_logger->info("sagepay->getAddressResult: " . $sage->getAddressResult());
                $this->_logger->info("sagepay->getPostcodeResult: " . $sage->getPostcodeResult());
                $this->_logger->info("sagepay->getDeclineCode: " . $sage->getDeclineCode());
                $this->_logger->info("sagepay->getBankAuthCode: " . $sage->getBankAuthCode());
                $this->_logger->info("sagepay->getPayerStatus: " . $sage->getPayerStatus());
            }
            if ($gateway_name == "optimal_hosted") {
                $optimalTransaction = unserialize($payment->getAdditionalInformation('transaction'));
                if ($optimalTransaction) {
                    $this->_logger->info("Optimal transaction: ");
                    $this->_logger->info("transaction->cvdVerification: " . $optimalTransaction->cvdVerification);
                    $this->_logger->info("transaction->houseNumberVerification: " . $optimalTransaction->houseNumberVerification);
                    $this->_logger->info("transaction->zipVerification: " . $optimalTransaction->zipVerification);
                } else {
                    $this->_logger->info("Optimal gateway but no transaction found");
                }
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }

    /**
     * @param $order
     */
    public function logInvoice($order)
    {
        try {
            $this->_logger->debug("Order " . $order->getId() . " parameters relevant to invoicing failure:");
            $this->_logger->debug("Order state: " . $order->getState());
            $this->_logger->debug("Order status: " . $order->getStatus());
            $this->_logger->debug("UNHOLD action flag: " . $order->getActionFlag(Order::ACTION_FLAG_UNHOLD));
            $this->_logger->debug("INVOICE action flag: " . $order->getActionFlag(Order::ACTION_FLAG_INVOICE));
            foreach ($order->getAllItems() as $item) {
                $this->_logger->debug("item " . $item->getProductId() . " - qty: " . $item->getQtyToInvoice() . "  locked: " . $item->getLockedDoInvoice());
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }

    /**
     * @param $message
     */
    public function log($message)
    {
        $this->_logger->info($message);
    }

    /**
     * @param $message
     */
    public function logException($message)
    {
        $this->_logger->critical($message);
    }
}
