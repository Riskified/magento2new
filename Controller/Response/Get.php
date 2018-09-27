<?php

namespace Riskified\Decider\Controller\Response;

use \Riskified\DecisionNotification;

class Get extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Riskified\Decider\Api\Order
     */
    private $apiOrderLayer;

    /**
     * @var \Riskified\Decider\Api\Api
     */
    private $api;

    /**
     * @var \Riskified\Decider\Api\Log
     */
    private $apiLogger;

    /**
     * Get constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Riskified\Decider\Api\Api $api
     * @param \Riskified\Decider\Api\Order $apiOrder
     * @param \Riskified\Decider\Api\Log $apiLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Riskified\Decider\Api\Api $api,
        \Riskified\Decider\Api\Order $apiOrder,
        \Riskified\Decider\Api\Log $apiLogger
    ) {
        parent::__construct($context);
        $this->api = $api;
        $this->apiLogger = $apiLogger;
        $this->apiOrderLayer = $apiOrder;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        $logger = $this->apiLogger;

        $logger->log(
            __("Riskified extension endpoint start")
        );

        $statusCode = 200;
        $id = null;
        $msg = null;
        try {
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
                    $statusCode = 400;
                    $msg = 'Could not find order to update.';
                } else {
                    $this->apiOrderLayer->update(
                        $order,
                        $notification->status,
                        $notification->oldStatus,
                        $notification->description
                    );
                    $statusCode = 200;
                    $msg = 'Order-Update event triggered.';
                }
            }
        } catch (\Riskified\DecisionNotification\Exception\AuthorizationException $e) {
            $logger->logException($e);
            $statusCode = 401;
            $msg = 'Authentication Failed.';
        } catch (\Riskified\DecisionNotification\Exception\BadPostJsonException $e) {
            $logger->logException($e);
            $statusCode = 400;
            $msg = "JSON Parsing Error.";
        } catch (\Exception $e) {
            $logger->log("ERROR: while processing notification for order $id");
            $logger->logException($e);
            $statusCode = 500;
            $msg = "Internal Error";
        }
        $logger->log($msg);
        $response->setHttpResponseCode($statusCode);
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody('{ "order" : { "id" : "' . $id . '", "description" : "' . $msg . '" } }');
        $response->sendResponse();
        exit;
    }
}
