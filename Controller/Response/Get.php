<?php

namespace Riskified\Decider\Controller\Response;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Riskified\Decider\Model\Api\Log;
use Riskified\Decider\Model\Api\Order;
use \Riskified\DecisionNotification;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Api\Log as LogApi;
use Magento\Framework\Controller\ResultFactory;

class Get extends \Magento\Framework\App\Action\Action
{

    const STATUS_OK = 200;
    const STATUS_BAD = 400;
    const STATUS_UNAUTHORIZED = 401;
    const STATUS_INTERNAL_SERVER = 500;

    /**
     * @var OrderApi
     */
    private $apiOrderLayer;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var LogApi
     */
    private $apiLogger;

    /**
     * Get constructor.
     *
     * @param Context $context
     * @param Api $api
     * @param OrderApi $apiOrder
     * @param LogApi $apiLogger
     */
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
        $request = $this->getRequest();
        $logger = $this->apiLogger;

        $logger->log(
            __("Riskified extension endpoint start")
        );

        $id = null;
        $msg = null;
        try {
            $this->api->initSdk();
            $notification = $this->api->parseRequest($request);
            $id = $notification->id;
            if ($notification->status == 'test' && $id == 0) {
                $statusCode = 200;
                $msg = __('Test notification received successfully');

                $logger->log(
                    sprintf(
                        __("Test Notification received: %s"),
                        serialize($notification)
                    )
                );
            } else {
                $logger->log(
                    sprintf(
                        __("Notification received: %s"),
                        serialize($notification)
                    )
                );

                /** @var \Magento\Sales\Api\Data\OrderInterface $order */
                $order = $this->apiOrderLayer->loadOrderByOrigId($id);

                if (!$order || !$order->getId()) {
                    $logger->log(
                        sprintf(
                            "ERROR: Unable to load order (%s)",
                            $id
                        )
                    );
                    $statusCode = self::STATUS_BAD;
                    $msg = 'Could not find order to update.';
                } else {
                    $this->apiOrderLayer->update(
                        $order,
                        $notification->status,
                        $notification->oldStatus,
                        $notification->description
                    );
                    $statusCode = self::STATUS_OK;
                    $msg = 'Order-Update event triggered.';
                }
            }
        } catch (\Riskified\DecisionNotification\Exception\AuthorizationException $e) {
            $logger->logException($e);
            $statusCode = self::STATUS_UNAUTHORIZED;
            $msg = 'Authentication Failed.';
        } catch (\Riskified\DecisionNotification\Exception\BadPostJsonException $e) {
            $logger->logException($e);
            $statusCode = self::STATUS_BAD;
            $msg = "JSON Parsing Error.";
        } catch (\Exception $e) {
            $logger->log("ERROR: while processing notification for order $id");
            $logger->logException($e);
            $statusCode = self::STATUS_INTERNAL_SERVER;
            $msg = "Internal Error";
        }
        $logger->log($msg);

        $this->getResponse()->setHttpResponseCode($statusCode);

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData([
            "order" => [
                "id" => $id,
                "description" => $msg
            ]
        ]);

        return $resultJson;
    }
}
