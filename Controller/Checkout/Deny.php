<?php

namespace Riskified\Decider\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Api\Order;
use \Riskified\DecisionNotification;
use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;

class Deny extends Action
{
    private $logger;
    private $checkoutSession;
    private $orderApi;

    public function __construct(Context $context, Log $logger, Session $checkoutSession, Order $orderApi)
    {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->orderApi = $orderApi;

        parent::__construct($context);
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $this->logger->log('Checkout Denied request, quote_id: ' . $this->checkoutSession->getQuoteId());
            $this->checkoutSession->getQuote()->setQuoteId($this->checkoutSession->getQuote()->getId());
            $this->orderApi->post(
                $this->checkoutSession->getQuote(),
                Api::ACTION_CHECKOUT_DENIED
            );

            return $result->setData([
                'success' => true
            ]);
        } catch (\Exception $e) {
            $this->logger->logException($e);
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
