<?php

namespace Riskified\Decider\Model\Api\Order;

use Riskified\Decider\Model\Api\Order\PaymentProcessor\AbstractPayment;
use Riskified\OrderWebhook\Model;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Registry;
use Riskified\Decider\Model\Api\Config as ApiConfig;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Message\ManagerInterface;
use Magento\Customer\Model\Customer;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\ResourceModel\GroupRepository;

class Helper
{
    private $_order;

    /**
     * @var Log
     */
    private $_apiLogger;

    /**
     * @var Monolog
     */
    private $_logger;

    /**
     * @var ApiConfig
     */
    private $_apiConfig;

    /**
     * @var ManagerInterface
     */
    private $_messageManager;

    /**
     * @var Customer
     */
    private $customer;

    /**
     * @var OrderCollectionFactory
     */
    private $_orderFactory;

    /**
     * @var StoreManagerInterface
     */
    private $_storeManager;

    /**
     * @var State
     */
    private $state;

    /**
     * @var PaymentProcessorFactory
     */
    private $paymentProcessorFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var Header
     */
    private $httpHeader;

    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var Customer
     */
    private $_customerFactory;

    /**
     * @var CustomerGroupFactory
     */
    private $_groupRepository;

    /**
     * Helper constructor.
     *
     * @param Monolog $logger
     * @param ApiConfig $apiConfig
     * @param Log $apiLogger
     * @param ManagerInterface $messageManager
     * @param Customer $customer
     * @param OrderCollectionFactory $orderFactory
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PaymentProcessorFactory $paymentProcessorFactory
     * @param State $state
     * @param ResolverInterface $localeResolver
     * @param Header $httpHeader
     * @param Registry $registry
     */
    public function __construct(
        GroupRepository $groupRepository,
        \Magento\Customer\Model\Customer $customerFactory,
        Monolog $logger,
        ApiConfig $apiConfig,
        Log $apiLogger,
        ManagerInterface $messageManager,
        Customer $customer,
        OrderCollectionFactory $orderFactory,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        PaymentProcessorFactory $paymentProcessorFactory,
        State $state,
        ResolverInterface $localeResolver,
        Header $httpHeader,
        Registry $registry
    ) {
        $this->_customerFactory = $customerFactory;
        $this->_groupRepository = $groupRepository;
        $this->_logger = $logger;
        $this->_messageManager = $messageManager;
        $this->_apiConfig = $apiConfig;
        $this->_apiLogger = $apiLogger;
        $this->customer = $customer;
        $this->_orderFactory = $orderFactory;
        $this->_storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->paymentProcessorFactory = $paymentProcessorFactory;
        $this->state = $state;
        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;
        $this->registry = $registry;
    }

    /**
     * @param $model
     */
    public function setOrder($model)
    {
        $this->_order = $model;
    }

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function setCheckoutSession($checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * @return null|string
     */
    public function getOrderOrigId()
    {
        if (!$this->getOrder()) {
            return null;
        }
        return $this->getOrder()->getId() . '_' . $this->getOrder()->getIncrementId();
    }

    /**
     * @return null|Model\DiscountCode
     * @throws \Exception
     */
    public function getDiscountCodes()
    {
        $code = $this->getOrder()->getDiscountDescription();
        $amount = $this->getOrder()->getDiscountAmount();
        if ($amount && $code) {
            return new Model\DiscountCode(array_filter(array(
                'code' => $code,
                'amount' => $amount
            )));
        }

        return null;
    }

    /**
     * @return null|Model\Address
     */
    public function getShippingAddress()
    {
        $mageAddr = $this->getOrder()->getShippingAddress();
        return $this->getAddress($mageAddr);
    }

    /**
     * @return null|Model\Address
     */
    public function getBillingAddress()
    {
        $mageAddr = $this->getOrder()->getBillingAddress();
        return $this->getAddress($mageAddr);
    }

    /**
     * @return Model\ClientDetails
     */
    public function getClientDetails()
    {
        return new Model\ClientDetails(array_filter(array(
            'accept_language' => $this->localeResolver->getLocale(),
            'user_agent' => $this->httpHeader->getHttpUserAgent()
        ), 'strlen'));
    }

    /**
     * @return Model\Customer
     * @throws \Exception
     */
    public function getCustomer()
    {
        $customer_id = strval($this->getOrder()->getCustomerId());
        $customer_props = array(
            'id' => $customer_id,
            'email' => $this->getOrder()->getCustomerEmail(),
            'first_name' => $this->getOrder()->getCustomerFirstname(),
            'last_name' => $this->getOrder()->getCustomerLastname(),
            'note' => $this->getOrder()->getCustomerNote(),
        );
        if ($customer_id) {
            $customer_details = $this->customer->load($customer_id);
            $customer_props['created_at'] = $this->formatDateAsIso8601($customer_details->getCreatedAt());
            $customer_props['updated_at'] = $this->formatDateAsIso8601($customer_details->getUpdatedAt());
            $customer_props['account_type'] = $this->getCustomerGroupCode($customer_details->getGroupId());
            try {
                $customer_orders = $this->_orderFactory->create()->addFieldToFilter('customer_id', $customer_id);
                $customer_orders_count = $customer_orders->getSize();
                $customer_props['orders_count'] = $customer_orders_count;
                if ($customer_orders_count) {
                    $customer_props['last_order_id'] = $customer_orders->getLastItem()->getId();
                    $total_spent = $customer_orders
                        ->addExpressionFieldToSelect('sum_total', 'SUM(base_grand_total)', 'base_grand_total')
                        ->fetchItem()->getSumTotal();
                    $customer_props['total_spent'] = $total_spent;
                }
            } catch (\Exception $e) {
                $this->_logger->critical($e);
                $this->_messageManager->addError('Riskified extension: ' . $e->getMessage());
            }
        }
        return new Model\Customer(array_filter($customer_props, 'strlen'));
    }

    /**
     * @param $customer
     * @return string
     */
    public function getCustomerGroupCode($groupId)
    {
        $customerGroup = $this->_groupRepository->getById($groupId);
        $code = $customerGroup->getCode();

        return $code;
    }

    /**
     * @return array
     */
    public function getLineItems()
    {
        $line_items = array();

        foreach ($this->getOrder()->getAllVisibleItems() as $key => $item) {
            $line_items[] = $this->getPreparedLineItem($item);

        }
        return $line_items;
    }

    /**
     * @return array
     */
    public function getAllLineItems($object = null)
    {
        $line_items = array();

        if ($object === null) {
            $object = $this->getOrder();
        }

        foreach ($object->getAllItems() as $key => $item) {
            $line_items[] = $this->getPreparedLineItem($item);
        }

        return $line_items;
    }

    public function getAllShipmentItems($object)
    {
        $line_items = [];

        foreach ($object->getItems() as $key => $item) {
            $orderItem = $item->getOrderItem();

            if ($orderItem->getProductType() == "configurable") {
                continue;
            } else {
                $parentItem = $orderItem->getParentItem();

                if ($parentItem) {
                    $orderItem->setPrice($parentItem->getPrice());
                }
            }

            $line_items[] = $this->getPreparedLineItem($orderItem);
        }

        return $line_items;
    }

    /**
     * @param $item
     * @return Model\LineItem
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getPreparedLineItem($item)
    {
        $prod_type = "physical";
        $category = null;
        $sub_categories = null;
        $brand = null;
        $product = $item->getProduct();

        if ($product) {
            $categories = [];
            $sub_categories = [];
            $category_ids = $product->getCategoryIds();

            foreach ($category_ids as $categoryId) {
                $cat = $this->categoryRepository->get($categoryId);
                if ($cat->getLevel() == 2) {
                    $categories[] = $cat->getName();
                } elseif ($cat->getLevel() >= 3) {
                    $sub_categories[] = $cat->getName();
                }
            }

            if (empty($category_ids)) {
                $store_root_category_id = $this->_storeManager->getStore()->getRootCategoryId();
                $root_category = $this->categoryRepository->get($store_root_category_id);
                $categories[] = $root_category->getName();
            }

            if ($product->getManufacturer()) {
                $brand = $product->getResource()->getAttribute('manufacturer')->getFrontend()->getValue($product);
            }
        }

        if ($item->getIsVirtual()) {
            $prod_type = "digital";
        }

        $line_item = new Model\LineItem(array_filter(array(
            'price' => floatval($item->getPrice()),
            'quantity' => intval($item->getQtyOrdered()),
            'title' => $item->getName(),
            'sku' => $item->getSku(),
            'product_id' => $item->getItemId(),
            'grams' => $item->getWeight(),
            'product_type' => $prod_type,
            'brand' => $brand,
            'category' => (isset($categories) && !empty($categories)) ? implode('|', $categories) : '',
            'sub_category' => (isset($sub_categories) && !empty($sub_categories)) ? implode('|', $sub_categories) : '',
            'required_shipping' => $item->getIsVirtual() ? true : false
        ), 'strlen'));

        return $line_item;
    }

    /**
     * @param $address
     * @return null|Model\Address
     * @throws \Exception
     */
    public function getAddress($address)
    {
        if (!$address) {
            return null;
        }
        $street = $address->getStreet();
        $address_1 = (!is_null($street) && array_key_exists('0', $street)) ? $street['0'] : null;
        $address_2 = (!is_null($street) && array_key_exists('1', $street)) ? $street['1'] : null;

        $firstName = $address->getFirstname();

        if (is_object($firstName)) {
            $firstName = $firstName->getText();
        }

        $addrArray = array_filter(array(
            'first_name' => $firstName,
            'last_name' => $address->getLastname(),
            'name' => $address->getFirstname() . " " . $address->getLastname(),
            'company' => $address->getCompany(),
            'address1' => $address_1,
            'address2' => $address_2,
            'city' => $address->getCity(),
            'country_code' => $address->getCountryId(),
            'province' => $address->getRegion(),
            'zip' => $address->getPostcode(),
            'phone' => $address->getTelephone(),
        ), 'strlen');

        if (!$addrArray) {
            return null;
        }
        return new Model\Address($addrArray);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getRefundDetails()
    {
        $order = $this->getOrder();
        $creditMemos = $order->getCreditmemosCollection();
        $refundObjectCollection = array();
        if ($creditMemos->getSize() > 0) {
            foreach ($creditMemos->getData() as $memo) {
                $refundObjectCollection[] = new Model\RefundDetails(array_filter([
                    'refund_id' => $memo['increment_id'],
                    'amount' => $memo['subtotal'],
                    'currency' => $memo['base_currency_code'],
                    'refunded_at' => $memo['created_at'],
                    'reason' => $memo['customer_note']
                ], 'strlen'));

            }
        }

        return $refundObjectCollection;
    }

    /**
     * @return null|Model\PaymentDetails
     * @throws \Exception
     */
    public function getPaymentDetails()
    {
        $payment = $this->getOrder()->getPayment();
        if (!$payment) {
            return null;
        }

        if ($this->_apiConfig->isLoggingEnabled()) {
            $this->_apiLogger->payment($this->getOrder());
        }
        $paymentData = [];
        try {
            $paymentProcessor = $this->getPaymentProcessor($this->getOrder());
            $paymentData = $paymentProcessor->getDetails();
        } catch (\Exception $e) {
            $this->_apiLogger->log(__(
                'Riskified: %1',
                $e->getMessage()
            ));
        }

        $this->preparePaymentData($payment, $paymentData);

        if (isset($paymentProcessor)
            && $paymentProcessor instanceof \Riskified\Decider\Model\Api\Order\PaymentProcessor\Paypal
        ) {
            return new Model\PaymentDetails(array_filter(array(
                'authorization_id' => $paymentData['transaction_id'],
                'payer_email' => $paymentData['payer_email'],
                'payer_status' => isset($paymentData['payer_status']) ? $paymentData['payer_status'] : '',
                'payer_address_status' => $paymentData['payer_address_status'],
                'protection_eligibility' => $paymentData['protection_eligibility'],
                'payment_status' => $paymentData['payment_status'],
                'pending_reason' => $paymentData['pending_reason']
            ), 'strlen'));
        }

        return new Model\PaymentDetails(array_filter(array(
            'authorization_id' => $paymentData['transaction_id'],
            'avs_result_code' => $paymentData['avs_result_code'],
            'cvv_result_code' => $paymentData['cvv_result_code'],
            'credit_card_number' => $paymentData['credit_card_number'],
            'credit_card_company' => $paymentData['credit_card_company'],
            'credit_card_bin' => $paymentData['credit_card_bin']
        ), 'strlen'));
    }

    /**
     * @param $order
     *
     * @return PaymentProcessor\AbstractPayment
     */
    public function getPaymentProcessor($order)
    {
        return $this->paymentProcessorFactory->create($order);
    }

    /**
     * Fill empty fields.
     *
     * @param $payment
     * @param $paymentData
     */
    public function preparePaymentData($payment, &$paymentData)
    {
        if (!isset($paymentData['transaction_id'])) {
            $paymentData['transaction_id'] = $payment->getTransactionId();
        }
        if (!isset($paymentData['cvv_result_code'])) {
            $paymentData['cvv_result_code'] = $payment->getCcCidStatus();
        }
        if (!isset($paymentData['credit_card_number'])) {
            $paymentData['credit_card_number'] = $payment->getCcLast4();
        }
        if (!isset($paymentData['credit_card_company'])) {
            $paymentData['credit_card_company'] = $payment->getCcType();
        }
        if (!isset($paymentData['avs_result_code'])) {
            $paymentData['avs_result_code'] = $payment->getCcAvsStatus();
        }

        if (!isset($paymentData['credit_card_bin']) || !$paymentData['credit_card_bin']) {
            $paymentData['credit_card_bin'] = $this->checkoutSession->getRiskifiedBin();
            $this->checkoutSession->unsRiskifiedBin();
        }
        if (!isset($paymentData['credit_card_bin']) || !$paymentData['credit_card_bin']) {
            $paymentData['credit_card_bin'] = $this->registry->registry('riskified_cc_bin');
        }
        if (isset($paymentData['credit_card_bin'])) {
            $paymentData['credit_card_bin'] = "XXXX-XXXX-XXXX-" . $paymentData['credit_card_bin'];
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getShippingLines()
    {
        return [
            [
                'price' => floatval($this->getOrder()->getShippingAmount()),
                'title' => strip_tags($this->getOrder()->getShippingDescription()),
                'code' => $this->getOrder()->getShippingMethod()
            ]
        ];
    }

    /**
     * @return null|string
     */
    public function getCancelledAt()
    {
        $commentCollection = $this->getOrder()->getStatusHistoryCollection();
        foreach ($commentCollection as $comment) {
            if ($comment->getStatus() == \Magento\Sales\Model\Order::STATE_CANCELED) {
                return 'now';
            }
        }
        return null;
    }

    /**
     * @return Model\OrderCancellation
     * @throws \Exception
     */
    public function getOrderCancellation()
    {
        $orderCancellation = new Model\OrderCancellation(array_filter(array(
            'id' => $this->getOrderOrigId(),
            'cancelled_at' => $this->formatDateAsIso8601($this->getCancelledAt()),
            'cancel_reason' => 'Cancelled by merchant'
        )));
        return $orderCancellation;
    }

    /**
     * @return Model\Fulfillment
     * @throws \Exception
     */
    public function getOrderFulfillments($createdShipment = null)
    {
        $fulfillments = array();
        $shipmentCollection = $this->getOrder()->getShipmentsCollection();

        foreach ($shipmentCollection as $shipment) {
            $tracking = $shipment->getTracksCollection()->getFirstItem();
            $comment = $shipment->getCommentsCollection()->getFirstItem();
            $payload = array(
                "fulfillment_id" => $shipment->getIncrementId(),
                "created_at" => $this->formatDateAsIso8601($shipment->getCreatedAt()),
                "status" => "success",
                "tracking_company" => $tracking->getTitle(),
                "tracking_numbers" => $tracking->getTrackNumber(),
                "message" => $comment->getComment(),
                "line_items" => $this->getAllShipmentItems($shipment)
            );
            if ($shipment->getId() == $createdShipment->getId()) {
                $payload['line_items'] = $this->getAllShipmentItems($createdShipment);
            }

            $fulfillments[] = new Model\FulfillmentDetails(array_filter($payload));
        }

        $orderFulfillments = new Model\Fulfillment(array_filter(array(
            'id' => $this->getOrderOrigId(),
            'fulfillments' => $fulfillments,
        )));

        return $orderFulfillments;
    }

    /**
     * @return string
     */
    public function getRemoteIp()
    {
        $this->_apiLogger->log("remote ip: " . $this->getOrder()->getRemoteIp() .
            ", x-forwarded-ip: " . $this->getOrder()->getXForwardedFor());

        $forwardedIp = $this->getOrder()->getXForwardedFor();
        $forwardeds = preg_split("/,/", $forwardedIp, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($forwardeds)) {
            return trim($forwardeds[0]);
        }
        $remoteIp = $this->getOrder()->getRemoteIp();
        $remotes = preg_split("/,/", $remoteIp, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($remotes)) {
            if (is_array($remotes) && count($remotes) > 1) {
                return trim($remotes[0]);
            } else {
                foreach ($remotes as $k => $val) {
                    $remotes[$k] = trim($val);
                }

                return join(',', $remotes);
            }
        }
        return $remoteIp;
    }

    /**
     * @param $dateStr
     *
     * @return false|null|string
     */
    public function formatDateAsIso8601($dateStr)
    {
        return ($dateStr == null) ? null : date('c', strtotime($dateStr));
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->state->getAreaCode() === 'adminhtml';
    }
}
