<?php

namespace Riskified\Decider\Controller\Response;

use Magento\Checkout\Model\Session;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Api\Order;
use \Riskified\DecisionNotification;

class Deny extends \Magento\Framework\App\Action\Action
{
    public function __construct(\Magento\Framework\App\Helper\Context $context, Log $logger, Session $checkoutSession, Order $orderApi)
    {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->orderApi = $orderApi;
    }

    /**
     * Execute.
     */
    public function execute()
    {

    }
}
