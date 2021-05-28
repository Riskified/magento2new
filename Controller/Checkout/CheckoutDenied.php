<?php
declare(strict_types=1);

namespace Riskified\Decider\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Api\Order;

class CheckoutDenied extends Action implements HttpPostActionInterface
{
    /**
     * @var Log
     */
    private $logger;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Order
     */
    private $orderApi;

    /**
     * @param Log $logger
     * @param Session $checkoutSession
     * @param Order $orderApi
     */
    public function __construct(Context $context, Log $logger, Session $checkoutSession, Order $orderApi)
    {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->orderApi = $orderApi;
    }

    /**
     * @return string
     */
    public function execute(): Json
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
