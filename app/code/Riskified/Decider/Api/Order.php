<?php
namespace Riskified\Decider\Api;
use Riskified\Common\Signature;
use Riskified\OrderWebhook\Model;
use Riskified\OrderWebhook\Transport;

class Order
{
    private $_api;
    private $_orderHelper;
    private $_context;
    private $_eventManager;
    private $_messageManager;
    private $_backendAuthSession;

    public function __construct(
        Api $api,
        Helper\Order $orderHelper,
        Config $apiConfig,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->_api = $api;
        $this->_orderHelper = $orderHelper;
        $this->_apiConfig           = $apiConfig;
        $this->_context             = $context;
        $this->_eventManager        = $context->getEventManager();
        $this->_backendAuthSession  = $backendAuthSession;
        $this->_messageManager      = $messageManager;

        $this->_api->initSdk();
    }
    public function postOrder($order, $action) {
        $transport = $this->_api->getTransport();
        $this->_orderHelper->setOrder($order);

        $eventData = array(
            'order' => $order,
            'action' => $action
        );
        try {
            switch ($action) {
                case Api::ACTION_CREATE:
                    $orderForTransport = $this->getOrder($order);
                    $response = $transport->createOrder($orderForTransport);
                    break;
                case Api::ACTION_UPDATE:
                    $orderForTransport = $this->getOrder($order);
                    $response = $transport->updateOrder($orderForTransport);
                    break;
                case Api::ACTION_SUBMIT:
                    $orderForTransport = $this->getOrder($order);
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

    private function getOrder($model) {
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

    public function postHistoricalOrders($models) {
        $orders = array();

        foreach ($models as $model) {
            $orders[] = $this->getOrder($model);
        }

        $msgs = $this->_api->getTransport()->sendHistoricalOrders($orders);
        return "Successdecidery uploaded ".count($msgs)." orders.".PHP_EOL;
    }

    public function scheduleSubmissionRetry(\Magento\Sales\Model\Order $order, $action)
    {
    }
}