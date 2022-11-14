<?php

namespace Riskified\Decider\Controller\Advice;

class Deny extends \Riskified\Decider\Controller\AdviceAbstract
{
    /**
     * Function fetches post data from order checkout payment step.
     * When 'mode' parameter is present data comes from 3D Secure Payment Authorisation Refuse and refusal details are saved in quotePayment table (additional_data). Order state is set as 'ACTION_CHECKOUT_DENIED'.
     * As a response validation status and message are returned.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Riskified\OrderWebhook\Exception\CurlException
     * @throws \Riskified\OrderWebhook\Exception\UnsuccessfulActionException
     */
    public function execute()
    {
        if ($this->isEnabled() === false) {
            return  $this->resultJsonFactory->create()->setData(['status' => 'disabled']);
        }

        $payload = $this->request->getParams();
        $quoteId = $this->getQuoteId($payload['quote_id']);
        $quote = $this->cartRespository->get($quoteId);

        if (!is_null($quote)) {
            $message = __('deny_controller_deny') . $quoteId;

            $payload['date'] = date('Y-m-d H:i:s', time());
            $this->updateQuotePaymentDetailsInDb($quote, $payload);
            //Riskified defined order as fraud - order data is send to Riskified
            $this->sendDeniedOrderToRiskified($quote);
            $this->logger->log($message);
        } else {
            $message = __('deny_controller_not_found') . $quoteId;
            $this->logger->log($message);
        }

        return  $this->resultJsonFactory->create()->setData(['message' => $message]);
    }

    /**
     * Saves quote payment details (additional data).
     * @param $quote
     * @param $paymentDetails
     * @throws \Exception
     */
    protected function updateQuotePaymentDetailsInDb($quote, $paymentDetails)
    {
        if (isset($quote)) {
            $this->logger->log(__('advise_log_quote_found') . $quote->getEntityId());
            $quotePayment = $quote->getPayment();
            $additionalData = $quotePayment->getAdditionalData();
            //avoid overwriting quotePayment additional data
            if (!is_array($additionalData)) {
                $additionalData = [];
            }
            $additionalData['3d_secure'] = $paymentDetails;
            $additionalData = json_encode($additionalData);
            try {
                $quotePayment->setAdditionalData($additionalData);
                $quotePayment->save();
            } catch (\RuntimeException $e) {
                $this->logger->log(__('advise_log_cannot_save') . $e->getMessage());
            }
        } else {
            $this->logger->log(__('advise_log_no_quote_found') . $quote->getEntityId());
        }
    }
}
