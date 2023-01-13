<?php

namespace Riskified\Decider\Controller\Response;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultFactory;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\DecisionFactory;
use Riskified\Decider\Model\Api\Log as LogApi;
use Riskified\Decider\Model\Api\Order as OrderApi;

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
     * @var Config
     */
    private $config;
    /**
     * @var DecisionRepositoryInterface
     */
    private $decisionRepository;

    /**
     * @var DecisionFactory
     */
    private DecisionFactory $decisionFactory;

    /**
     * Get constructor.
     *
     * @param Context $context
     * @param Api $api
     * @param OrderApi $apiOrder
     * @param LogApi $apiLogger
     */
    public function __construct(
        Context $context,
        Api $api,
        OrderApi $apiOrder,
        LogApi $apiLogger,
        Config $config,
        DecisionFactory $decisionFactory,
        DecisionRepositoryInterface $decisionRepository
    ) {
        parent::__construct($context);
        $this->api = $api;
        $this->apiLogger = $apiLogger;
        $this->apiOrderLayer = $apiOrder;
        $this->config = $config;
        $this->decisionFactory = $decisionFactory;
        $this->decisionRepository = $decisionRepository;

        // CsrfAwareAction Magento2.3 compatibility
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $context->getRequest();
            if ($request instanceof HttpRequest && $request->isPost()) {
                $request->setParam('isAjax', true);
                $headers = $request->getHeaders();
                $headers->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
                $request->setHeaders($headers);
            }
        }

        parent::__construct($context);
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
                        json_encode($notification)
                    )
                );
            } else {
                $logger->log(
                    sprintf(
                        __("Notification received: %s"),
                        json_encode($notification)
                    )
                );

                /** @var \Magento\Sales\Api\Data\OrderInterface $order */
                $order = $this->apiOrderLayer->loadOrderByOrigId($id);

                if (!$order || !$order->getId()) {
                    $logger->log(
                        sprintf("ERROR: Unable to load order (%s)", $id)
                    );
                    $statusCode = self::STATUS_BAD;
                    $msg = 'Could not find order to update.';
                } else {
                    $decision = $this->decisionFactory->create();
                    $decision->setOrderId($order->getId());
                    $decision->setDecision($notification->status);
                    $decision->setDescription($notification->description);
                    $decision->setCreatedAt(date("Y-m-d H:i:s"));

                    $this->decisionRepository->save($decision);

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
