<?php
namespace Riskified\Decider\Api;
use Riskified\Common\Signature;
use Riskified\OrderWebhook\Model;
use Riskified\OrderWebhook\Transport;
use \Magento\Sales\Model\OrderFactory;

class Order
{
    private $_api;
    private $_orderHelper;
    private $_context;
    private $_eventManager;
    private $_messageManager;
    private $_backendAuthSession;
    private $_orderFactory;
    private $logger;

    public function __construct(
        Api $api,
        Order\Helper $orderHelper,
        Config $apiConfig,
        Log $logger,
        Log $logger,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        OrderFactory $salesOrderCollectionFactory

    ) {
        $this->_api                 = $api;
        $this->_orderHelper         = $orderHelper;
        $this->_apiConfig           = $apiConfig;
        $this->_context             = $context;
        $this->_eventManager        = $context->getEventManager();
        $this->_backendAuthSession  = $backendAuthSession;
        $this->_messageManager      = $messageManager;
        $this->_orderFactory        = $salesOrderCollectionFactory;
        $this->logger               = $logger;

        $this->_api->initSdk();
    }
    public function post($order, $action) {
        $transport = $this->_api->getTransport();
        $orderModelFactory = $this->_orderFactory->create()->loadByIncrementId($order->getIncrementId());
        if(!$orderModelFactory) {
            throw new \Exception("Order doesn't not exists");
        }
        $this->_orderHelper->setOrder($order);

        $eventData = array(
            'order' => $orderModelFactory,
            'action' => $action
        );
        try {
            switch ($action) {
                case Api::ACTION_CREATE:
                    $orderForTransport = $this->load($order);
                    $response = $transport->createOrder($orderForTransport);
                    break;
                case Api::ACTION_UPDATE:
                    $orderForTransport = $this->load($order);
                    $response = $transport->updateOrder($orderForTransport);
                    break;
                case Api::ACTION_SUBMIT:
                    $orderForTransport = $this->load($order);
                    $response = $transport->submitOrder($orderForTransport);
                    break;
                case Api::ACTION_CANCEL:
                    $orderForTransport = $this->_orderHelper->getOrderCancellation();
                    $response = $transport->cancelOrder($orderForTransport);
                    break;
            }
            $eventData['response'] = $response;

            $this->_messageManager->addSuccess('Riskified extension: The order was sent');
            $this->_eventManager->dispatch(
                'riskified_decider_post_order_success',
                $eventData
            );
        } catch(\Riskified\OrderWebhook\Exception\CurlException $curlException) {
            $this->_raiseOrderUpdateEvent($order, 'error',null, 'Error transferring order data to Riskified');
            $this->scheduleSubmissionRetry($order, $action);

            $this->_eventManager->dispatch(
                'riskified_decider_post_order_error',
                $eventData
            );
            throw $curlException;
        }
        catch (\Exception $e) {
            $this->_eventManager->dispatch(
                'riskified_decider_post_order_error',
                $eventData
            );
            throw $e;
        }
        return $response;
    }

    private function _raiseOrderUpdateEvent($order, $status, $oldStatus, $description) {
        $eventData = array(
            'order' => $order,
            'status' => $status,
            'old_status' => $oldStatus,
            'description' => $description
        );
        $this->_eventManager->dispatch(
            'riskified_decider_order_update',
            $eventData
        );

        $eventIdentifier = preg_replace("/[^a-z]/", '_', strtolower($status));

        $this->_eventManager->dispatch(
            'riskified_decider_order_update_' . $eventIdentifier,
            $eventData
        );
        return;
    }

    private function load($model) {
        $gateway = 'unavailable';
        if ($model->getPayment()) {
            $gateway = $model->getPayment()->getMethod();
        }
        $order_array = array(
            'id' => $this->_orderHelper->getOrderOrigId(),
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
            'discount_codes' =>$this->_orderHelper->getDiscountCodes($model),
            'taxes_included' => true,
            'total_tax' => $model->getBaseTaxAmount(),
            'total_weight' => $model->getWeight(),
            'cancelled_at' => $this->_orderHelper->formatDateAsIso8601($this->_orderHelper->getCancelledAt()),
            'financial_status' => $model->getState(),
            'fulfillment_status' => $model->getStatus(),
            'vendor_id' => $model->getStoreId(),
            'vendor_name' => $model->getStoreName()
        );

        if ($this->_orderHelper->getCustomerSession()->isLoggedIn()) {
            unset($order_array['browser_ip']);
            unset($order_array['cart_token']);
        }

        $order = new Model\Order(array_filter($order_array, 'strlen'));

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

    public function update($order, $status, $oldStatus, $description) {
        $this->logger->log('Dispatching event for order ' . $order->getId() . ' with status "' . $status .
            '" old status "' . $oldStatus . '" and description "' . $description . '"');
        $eventData = array(
            'order' => $order,
            'status' => $status,
            'old_status' => $oldStatus,
            'description' => $description
        );
        $this->_eventManager->dispatch(
            'riskified_full_order_update',
            $eventData
        );
        $eventIdentifier = preg_replace("/[^a-z]/", '_', strtolower($status));
        $this->_eventManager->dispatch(
            'riskified_full_order_update_' . $eventIdentifier,
            $eventData
        );
        return;
    }

    public function loadOrderByOrigId($full_orig_id) {
        if(!$full_orig_id) {
            return null;
        }
        $magento_ids = explode("_",$full_orig_id);
        $order_id = $magento_ids[0];
        $increment_id = $magento_ids[1];
        if ($order_id && $increment_id) {
            return $this->_orderFactory->create()->getCollection()
                ->addFieldToFilter('entity_id', $order_id)
                ->addFieldToFilter('increment_id',$increment_id)
                ->getFirstItem();
        }

        if(!$order_id && $increment_id) {
            return $this->_orderFactory->create()->loadByIncrementId($increment_id);
        }
        return $this->_orderFactory->create()->load($order_id);
    }

    public function postHistoricalOrders($models) {
        $orders = array();

        foreach ($models as $model) {
            $orders[] = $this->getOrder($model);
        }

        $msgs = $this->_api->getTransport()->sendHistoricalOrders($orders);
        return "Success decidery uploaded ".count($msgs)." orders.".PHP_EOL;
    }

    public function scheduleSubmissionRetry(\Magento\Sales\Model\Order $order, $action)
    {
    }
}