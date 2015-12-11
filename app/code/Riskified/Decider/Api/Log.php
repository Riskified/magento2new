<?php
namespace Riskified\Decider\Api;

class Log {
    private $_logger;

    public function __construct(\Riskified\Decider\Logger\Order $logger) {
        $this->_logger = $logger;
    }
    private function payment($model) {
        $this->_logger->debug("Payment info debug Logs:");
        try {
            $payment = $model->getPayment();
            $gateway_name = $payment->getMethod();
            $this->_logger->debug("Payment Gateway: ".$gateway_name);
            $this->_logger->debug("payment->getCcLast4(): ".$payment->getCcLast4());
            $this->_logger->debug("payment->getCcType(): ".$payment->getCcType());
            $this->_logger->debug("payment->getCcCidStatus(): ".$payment->getCcCidStatus());
            $this->_logger->debug("payment->getCcAvsStatus(): ".$payment->getCcAvsStatus());
            $this->_logger->debug("payment->getAdditionalInformation(): ".PHP_EOL.var_export($payment->getAdditionalInformation(), 1));
            $sage = $model->getSagepayInfo();

            if(is_object($sage)) {
                $this->_logger->debug("sagepay->getLastFourDigits(): ".$sage->getLastFourDigits());
                $this->_logger->debug("sagepay->last_four_digits: ".$sage->getData('last_four_digits'));
                $this->_logger->debug("sagepay->getCardType(): ".$sage->getCardType());
                $this->_logger->debug("sagepay->card_type: ".$sage->getData('card_type'));
                $this->_logger->debug("sagepay->getAvsCv2Status: ".$sage->getAvsCv2Status());
                $this->_logger->debug("sagepay->address_result: ".$sage->getData('address_result'));
                $this->_logger->debug("sagepay->getCv2result: ".$sage->getCv2result());
                $this->_logger->debug("sagepay->cv2result: ".$sage->getData('cv2result'));
                $this->_logger->debug("sagepay->getAvscv2: ".$sage->getAvscv2());
                $this->_logger->debug("sagepay->getAddressResult: ".$sage->getAddressResult());
                $this->_logger->debug("sagepay->getPostcodeResult: ".$sage->getPostcodeResult());
                $this->_logger->debug("sagepay->getDeclineCode: ".$sage->getDeclineCode());
                $this->_logger->debug("sagepay->getBankAuthCode: ".$sage->getBankAuthCode());
                $this->_logger->debug("sagepay->getPayerStatus: ".$sage->getPayerStatus());
            }
            if($gateway_name == "optimal_hosted") {
                $optimalTransaction = unserialize($payment->getAdditionalInformation('transaction'));
                if($optimalTransaction) {
                    $this->_logger->debug("Optimal transaction: ");
                    $this->_logger->debug("transaction->cvdVerification: ".$optimalTransaction->cvdVerification);
                    $this->_logger->debug("transaction->houseNumberVerification: ".$optimalTransaction->houseNumberVerification);
                    $this->_logger->debug("transaction->zipVerification: ".$optimalTransaction->zipVerification);
                }
                else {
                    $this->_logger->debug("Optimal gateway but no transaction found");
                }
            }
        } catch(\Exception $e) {
            $this->_logger->critical($e);
        }
    }
}