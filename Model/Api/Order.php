<?php

namespace Riskified\Decider\Model\Api;

use Riskified\OrderWebhook\Model;
use Magento\Framework\Registry;

class Order
{
    /**
     * @var Api
     */
    private $_api;

    /**
     * @var Order\Helper
     */
    private $_orderHelper;

    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    private $_context;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $_eventManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $_messageManager;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $_backendAuthSession;

    /**
     * @var Order\Log
     */
    private $logger;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $date;

    /**
     * @var \Riskified\Decider\Model\QueueFactory
     */
    private $queueFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Session\SessionManager
     */
    private $session;

    /**
     * @var Registry
     */
    private $registry;
    private $_apiConfig;

    /**
     * @var Config
     */
    private Config $_apiConfig;

    /**
     * Order constructor.
     *
     * @param Api $api
     * @param Order\Helper $orderHelper
     * @param Config $apiConfig
     * @param Order\Log $logger
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Backend\Model\Auth\Session $backendAuthSession
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Riskified\Decider\Model\QueueFactory $queueFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        Api $api,
        Order\Helper $orderHelper,
        Config $apiConfig,
        Order\Log $logger,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Riskified\Decider\Model\QueueFactory $queueFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        Registry $registry
    ) {
        $this->_api = $api;
        $this->_orderHelper = $orderHelper;
        $this->_apiConfig = $apiConfig;
        $this->_context = $context;
        $this->_eventManager = $context->getEventManager();
        $this->_backendAuthSession = $backendAuthSession;
        $this->_messageManager = $messageManager;
        $this->logger = $logger;
        $this->session = $sessionManager;
        $this->date = $date;
        $this->queueFactory = $queueFactory;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->registry = $registry;

        $this->_orderHelper->setCheckoutSession($checkoutSession);

        $this->_api->initSdk();
    }

    /**
     * @param $order
     * @param $action
     *
     * @return $this|object
     *
     * @throws \Exception
     * @throws \Riskified\OrderWebhook\Exception\CurlException
     * @throws \Riskified\OrderWebhook\Exception\MalformedJsonException
     */
    public function post($order, $action)
    {
        if (!$this->_apiConfig->isEnabled($order->getStoreId())) {
            return $this;
        }

        if (!$order) {
            throw new \Exception("Order doesn't not exists");
        }

        $this->_api->initSdk($order);
        $transport = $this->_api->getTransport();

        $this->_orderHelper->setOrder($order);
        $this->registry->register("riskified-order", $order, true);

        $eventData = [
            'order' => $order,
            'action' => $action
        ];
        try {
            switch ($action) {
                case Api::ACTION_CREATE:
                    $orderForTransport = $this->load($order);
                    $response = $transport->createOrder($orderForTransport);
                    break;
                case Api::ACTION_UPDATE:
                    $orderForTransport = $this->load($order);
                    $this->logger->log($orderForTransport->toJson());
                    $response = $transport->updateOrder($orderForTransport);
                    break;
                case Api::ACTION_SUBMIT:
                    $orderForTransport = $this->load($order);
                    $this->logger->log($orderForTransport->toJson());
                    $response = $transport->submitOrder($orderForTransport);
                    break;
                case Api::ACTION_CANCEL:
                    $orderForTransport = $this->_orderHelper->getOrderCancellation();
                    $this->logger->log($orderForTransport->toJson());
                    $response = $transport->cancelOrder($orderForTransport);
                    break;
                case Api::ACTION_FULFILL:
                    $this->_orderHelper->setOrder($order->getOrder());
                    $orderForTransport = $this->_orderHelper->getOrderFulfillments($order);

                    $order = $order->getOrder();
                    $eventData['order'] = $order->getOrder();

                    $this->logger->log($orderForTransport->toJson());
                    $response = $transport->fulfillOrder($orderForTransport);
                    break;
                case Api::ACTION_REFUND:
                    $orderForTransport = $this->loadRefund();
                    $this->logger->log($orderForTransport->toJson());
                    $response = $transport->refundOrder($orderForTransport);
                    break;
                case Api::ACTION_CHECKOUT_DENIED:
                    $checkoutForTransport = $this->loadQuote($order);
                    $this->logger->log($checkoutForTransport->toJson());
                    $response = $transport->deniedCheckout($checkoutForTransport);
                    break;
            }
            $eventData['response'] = $response;

            $this->_eventManager->dispatch(
                'riskified_decider_post_order_success',
                $eventData
            );
        } catch (\Riskified\OrderWebhook\Exception\CurlException $curlException) {
            $this->_raiseOrderUpdateEvent($order, 'error', null, 'Error transferring order data to Riskified');
            $this->scheduleSubmissionRetry($order, $action);

            $this->_eventManager->dispatch(
                'riskified_decider_post_order_error',
                $eventData
            );
            throw $curlException;
        } catch (\Riskified\OrderWebhook\Exception\MalformedJsonException $e) {
            if (strstr($e->getMessage(), "504") && strstr($e->getMessage(), "Status Code:")) {
                $this->_raiseOrderUpdateEvent($order, 'error', null, 'Error transferring order data to Riskified');
                $this->scheduleSubmissionRetry($order, $action);
            }
            $this->_eventManager->dispatch(
                'riskified_decider_post_order_error',
                $eventData
            );
            throw $e;
        } catch (\Exception $e) {
            $this->_eventManager->dispatch(
                'riskified_decider_post_order_error',
                $eventData
            );
            throw $e;
        }
        return $response;
    }

    /**
     * @param $order
     * @param $status
     * @param $oldStatus
     * @param $description
     *
     * @return $this
     */
    private function _raiseOrderUpdateEvent($order, $status, $oldStatus, $description)
    {
        $eventData = [
            'order' => $order,
            'status' => $status,
            'old_status' => $oldStatus,
            'description' => $description
        ];
        $this->_eventManager->dispatch(
            'riskified_decider_order_update',
            $eventData
        );

        $eventIdentifier = preg_replace("/[^a-z]/", '_', strtolower($status));

        $this->_eventManager->dispatch(
            'riskified_decider_order_update_' . $eventIdentifier,
            $eventData
        );

        return $this;
    }

    /**
     * @return Model\Refund
     * @throws \Exception
     */
    private function loadRefund()
    {
        $refund = new Model\Refund();
        $refund->id = strval($this->_orderHelper->getOrderOrigId());
        $refundDetails = $this->_orderHelper->getRefundDetails();
        $refund->refunds = array_filter($refundDetails, fn ($val) => $val !== null || $val !== false);

        return $refund;
    }

    private function loadQuote($model)
    {
        $gateway = 'unavailable';
        if ($model->getPayment()) {
            $gateway = $model->getPayment()->getMethod();
        }

        $order_array = [
            'id' => (int) $model->getId(),
            'name' => $model->getIncrementId(),
            'email' => $model->getCustomerEmail(),
            'created_at' => $this->_orderHelper->formatDateAsIso8601($model->getCreatedAt()),
            'currency' => $model->getOrderCurrencyCode(),
            'updated_at' => $this->_orderHelper->formatDateAsIso8601($model->getUpdatedAt()),
            'gateway' => $gateway,
            'browser_ip' => $this->_orderHelper->getRemoteIp(),
            'note' => $model->getCustomerNote(),
            'total_price' => $model->getGrandTotal(),
            'total_discounts' => $model->getDiscountAmount(),
            'subtotal_price' => $model->getBaseSubtotalInclTax(),
            'discount_codes' => $this->_orderHelper->getDiscountCodes($model),
            'taxes_included' => true,
            'total_tax' => $model->getBaseTaxAmount(),
            'total_weight' => $model->getWeight(),
            'cancelled_at' => $this->_orderHelper->formatDateAsIso8601($this->_orderHelper->getCancelledAt()),
            'financial_status' => $model->getState() == "adyen_authorized" ? "processing" : $model->getState(),
            'vendor_id' => $model->getStoreId(),
            'vendor_name' => $model->getStoreName(),
            'cart_token' => $this->session->getSessionId()
        ];

        $payload = array_filter($order_array, fn ($val) => $val !== null || $val !== false);

        $order = new Model\Checkout($payload);

        $order->customer = $this->_orderHelper->getCustomer();
        $order->shipping_address = $this->_orderHelper->getShippingAddress();
        $order->billing_address = $this->_orderHelper->getBillingAddress();
        $order->payment_details = $this->_orderHelper->getPaymentDetails();
        $order->line_items = $this->_orderHelper->getLineItems();

        if (!$this->_backendAuthSession->isLoggedIn()) {
            $order->client_details = $this->_orderHelper->getClientDetails();
        }
        return $order;
    }

    public function getCartToken($model): string
    {
        if (is_null($model->getRiskifiedCartToken())) {
            $cartToken = $this->session->getSessionId();
            $model->setRiskifiedCartToken($cartToken);
        } else {
            $cartToken = $model->getRiskifiedCartToken();
        }

        return $cartToken;
    }

    /**
     * @param $model
     *
     * @return Model\Order
     */
    private function load($model)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface $model */
        $gateway = 'unavailable';

        if ($model->getPayment()) {
            $gateway = $model->getPayment()->getMethod();
        }

        $order_array = [
            'id' => $this->_orderHelper->getOrderOrigId(),
            'name' => $model->getIncrementId(),
            'email' => $model->getCustomerEmail(),
            'created_at' => $this->_orderHelper->formatDateAsIso8601($model->getCreatedAt()),
            'currency' => $model->getOrderCurrencyCode(),
            'updated_at' => $this->_orderHelper->formatDateAsIso8601($model->getUpdatedAt()),
            'gateway' => $gateway,
            'browser_ip' => $this->_orderHelper->getRemoteIp(),
            'note' => $model->getCustomerNote(),
            'total_price' => floatval($model->getGrandTotal()),
            'total_discounts' => $model->getDiscountAmount(),
            'financial_status' => $model->getState() == "adyen_authorized" ? "processing" : $model->getState(),
            'fulfillment_status' => $model->getStatus(),
            'discount_codes' => $this->_orderHelper->getDiscountCodes(),
            'cancelled_at' => $this->_orderHelper->formatDateAsIso8601($this->_orderHelper->getCancelledAt()),
            'vendor_id' => strval($model->getStoreId()),
            'vendor_name' => $model->getStoreName(),
            'cart_token' => $this->getCartToken($model)
        ];

        if ($this->_orderHelper->isAdmin()) {
            unset($order_array['browser_ip']);
            unset($order_array['cart_token']);
            $order_array['source'] = 'admin';
        } else {
            $order_array['source'] = 'desktop_web';
        }

        $order = new Model\Order(
            array_filter($order_array, fn ($val) => $val !== null || $val !== false)
        );
        $order->customer = $this->_orderHelper->getCustomer();
        $order->shipping_address = $this->_orderHelper->getShippingAddress();
        $order->billing_address = $this->_orderHelper->getBillingAddress();
        $order->payment_details = $this->_orderHelper->getPaymentDetails();
        $order->line_items = $this->_orderHelper->getLineItems();
        $order->shipping_lines = $this->_orderHelper->getShippingLines();

        if (!$this->_backendAuthSession->isLoggedIn()) {
            $order->client_details = $this->_orderHelper->getClientDetails();
        }

        return $order;
    }

    /**
     * @param $order
     * @param $status
     * @param $oldStatus
     * @param $description
     *
     * @return $this|void
     */
    public function update($order, $status, $oldStatus, $description)
    {
        if (!$this->_apiConfig->isEnabled($order->getStoreId())) {
            return;
        }

        if (!$order) {
            return;
        }

        $this->registry->register("riskified-order", $order, true);

        $this->logger->log('Dispatching event for order ' . $order->getId() . ' with status "' . $status .
            '" old status "' . $oldStatus . '" and description "' . $description . '"');
        $eventData = [
            'order' => $order,
            'status' => $status,
            'old_status' => $oldStatus,
            'description' => $description
        ];

        $this->_eventManager->dispatch(
            'riskified_decider_order_update',
            $eventData
        );
        $eventIdentifier = preg_replace("/[^a-z]/", '_', strtolower($status));
        $this->_eventManager->dispatch(
            'riskified_decider_order_update_' . $eventIdentifier,
            $eventData
        );

        return $this;
    }

    /**
     * @param $full_orig_id
     *
     * @return bool|\Magento\Sales\Api\Data\OrderInterface|mixed|null
     */
    public function loadOrderByOrigId($full_orig_id)
    {
        if (!$full_orig_id) {
            return null;
        }
        $magento_ids = explode("_", $full_orig_id);

        /**
         * validate if provided is is matching
         */
        $order_id = false;
        $increment_id = false;

        if (isset($magento_ids[0])) {
            $order_id = $magento_ids[0];
        }

        if (isset($magento_ids[1])) {
            $increment_id = $magento_ids[1];
        }

        if ($order_id && $increment_id) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $increment_id, 'eq')
                ->addFilter('entity_id', $order_id, 'eq')
                ->setPageSize(1)
                ->create();

            $orderSearchResultList = $this->orderRepository->getList($searchCriteria);
            $orderList = $orderSearchResultList->getItems();

            if (is_array($orderList) && count($orderList) === 1) {
                return reset($orderList);
            } else {
                return false;
            }
        }

        if (!$order_id && $increment_id) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $increment_id, 'eq')->create();

            $orderSearchResultList = $this->orderRepository->getList($searchCriteria);
            $orderList = $orderSearchResultList->getItems();

            if (is_array($orderList) && count($orderList) === 1) {
                return reset($orderList);
            } else {
                return false;
            }
        }

        if ($order_id) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $order_id, 'eq')->create();

            $orderSearchResultList = $this->orderRepository->getList($searchCriteria);
            $orderList = $orderSearchResultList->getItems();

            if (is_array($orderList) && count($orderList) === 1) {
                return reset($orderList);
            } else {
                return false;
            }
        }

        return null;
    }

    /**
     * @param $models
     *
     * @return string|void
     */
    public function postHistoricalOrders($models)
    {
        if (!$this->_apiConfig->isEnabled()) {
            return;
        }
        $orders = [];

        foreach ($models as $model) {
            $orders[] = $this->getOrder($model);
        }

        $msgs = $this->_api->getTransport()->sendHistoricalOrders($orders);
        return "Success decidery uploaded " . count($msgs) . " orders." . PHP_EOL;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $action
     */
    public function scheduleSubmissionRetry(\Magento\Sales\Model\Order $order, $action)
    {
        $this->logger->log("Scheduling submission retry for order " . $order->getId());

        try {
            $existingRetries = $this->queueFactory->create()->getCollection()
                ->addFieldToFilter('order_id', $order->getId())
                ->addFieldToFilter('action', $action);

            if ($existingRetries->getSize() == 0) {
                $queue = $this->queueFactory->create();
                $queue->addData([
                    'order_id' => $order->getId(),
                    'action' => $action,
                    'updated_at' => $this->date->gmtDate()
                ])->save();

                $this->logger->log("New retry scheduled successfully");
            }
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * @param $order_ids
     *
     * @return int|void
     */
    public function sendOrders($order_ids)
    {
        if (!$this->_apiConfig->isEnabled()) {
            return;
        }
        $i = 0;
        foreach ($order_ids as $order_id) {
            $order = $this->orderRepository->get($order_id);
            try {
                $this->post($order, \Riskified\Decider\Model\Api\Api::ACTION_SUBMIT);
                $i++;
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
        }

        return $i;
    }
}
