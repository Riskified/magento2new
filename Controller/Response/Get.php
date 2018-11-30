<?php

namespace Riskified\Decider\Controller\Response;

use Magento\Framework\App\Action\Context;
use \Riskified\DecisionNotification;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Api\Log as LogApi;
use Magento\Framework\Controller\ResultFactory;

class Get extends \Magento\Framework\App\Action\Action
{
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
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Api $api,
        OrderApi $apiOrder,
        LogApi $apiLogger
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

        $this->getResponse()->setHttpResponseCode($statusCode);

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData([
            "id" => $id,
            "description" => $msg
        ]);

        return $resultJson;
    }
}