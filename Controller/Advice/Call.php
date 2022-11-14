<?php

namespace Riskified\Decider\Controller\Advice;

class Call extends \Riskified\Decider\Controller\AdviceAbstract
{
    /**
     * Function fetches post data from order checkout payment step.
     * When 'mode' parameter is present data comes from 3D Secure Payment Authorisation Refuse and refusal details are saved in quotePayment table (additional_data). Order state is set as 'ACTION_CHECKOUT_DENIED'.
     * In other cases collected payment data are send for validation to Riskified Advise Api and validation status is returned to frontend. Additionally when validation status is not 'captured' order state is set as 'ACTION_CHECKOUT_DENIED'.
     * As a response validation status and message are returned.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Riskified\OrderWebhook\Exception\CurlException
     * @throws \Riskified\OrderWebhook\Exception\UnsuccessfulActionException
     */
    public function execute()
    {
        if ($this->isEnabled() === false) {
            return $this->resultJsonFactory->create()->setData(['status' => 1]);
        }

        $payload = $this->request->getContent();

        parse_str($payload, $params);

        $quoteId = $this->getQuoteId($params['quote_id']);
        $quote = $this->cartRespository->get($quoteId);

        if (!$quote || !$quote->getId()) {
            return $this->resultJsonFactory->create()->setData(['status' => 9999, 'message' => "Quote does not exists"]);
        }

        $this->api->initSdk($quote);
        $this->adviceBuilder->build($params);
        $callResponse = $this->adviceBuilder->request();

        $this->logger->log(json_encode($callResponse));

        if (!isset($callResponse->checkout)) {
            $apiCallResponse = json_decode($callResponse);

            $logMessage = sprintf(
                'Payment Refused - Riskified error. Status: %s. Error content: %s',
                $apiCallResponse->status,
                $apiCallResponse->error
            );

            $this->logger->log($logMessage);

            return $this->resultJsonFactory->create()->setData(['status' => 9999]);
        }

        $status = $callResponse->checkout->status;

        if (!isset($callResponse->checkout->advice)) {
            if ($callResponse->checkout->action == "proceed") {
                $adviceCallStatus = 1;
            } else {
                $adviceCallStatus = 9999;
            }

            $message = $callResponse->checkout->description;
        } else {
            $authType = $callResponse->checkout->advice->recommendation;
            $this->session->setAdviceCallStatus($status);

            if ($status != "captured") {
                $adviceCallStatus = 3;
                $logMessage = 'Advice call denied - ' . $quoteId;

                $this->sendDeniedOrderToRiskified($quote);

                $this->logger->log($logMessage);
            } elseif ($status == "fraud") {
                $adviceCallStatus = 3;
            } else {
                if ($callResponse->checkout->action == 'decline') {
                    $adviceCallStatus = 3;
                    $message = __('declined');
                } else {
                    if ($authType == "sca") {
                        $adviceCallStatus = 0;
                        $message = "Enabled SCA";
                    } else {
                        $adviceCallStatus = 1;
                        $message = "Enabled TRA";
                    }
                }
            }
        }

        return $this->resultJsonFactory->create()->setData(['status' => $adviceCallStatus, 'message' => $message]);
    }
}
