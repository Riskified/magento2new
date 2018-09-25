<?php

namespace Riskified\Decider\Api\Order;

use Riskified\OrderWebhook\Model;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Riskified\Decider\Api\Order\PaymentProcessorFactory;

class Helper
{
    private $_order;
    private $_apiLogger;
    private $_logger;
    private $_apiConfig;
    private $_messageManager;
    private $_customerFactory;
    private $_orderFactory;
    private $_storeManager;

    /**
     * @var \Riskified\Decider\Api\Order\PaymentProcessorFactory
     */
    private $paymentProcessorFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    public function __construct(
        \Magento\Framework\Logger\Monolog $logger,
        \Riskified\Decider\Api\Config $apiConfig,
        Log $apiLogger,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Customer\Model\Customer $customerFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        PaymentProcessorFactory $paymentProcessorFactory
    ) {
        $this->_logger = $logger;
        $this->_messageManager = $messageManager;
        $this->_apiConfig = $apiConfig;
        $this->_apiLogger = $apiLogger;
        $this->_customerFactory = $customerFactory;
        $this->_orderFactory = $orderFactory;
        $this->_storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->paymentProcessorFactory = $paymentProcessorFactory;
    }

    public function setOrder($model)
    {
        $this->_order = $model;
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function getOrderOrigId()
    {
        if (!$this->getOrder()) {
            return null;
        }
        return $this->getOrder()->getId() . '_' . $this->getOrder()->getIncrementId();
    }

    public function getDiscountCodes()
    {
        $code = $this->getOrder()->getDiscountDescription();
        $amount = $this->getOrder()->getDiscountAmount();
        if ($amount && $code)
            return new Model\DiscountCode(array_filter(array(
                'code' => $code,
                'amount' => $amount
            )));
        return null;
    }

    public function getShippingAddress()
    {
        $mageAddr = $this->getOrder()->getShippingAddress();
        return $this->getAddress($mageAddr);
    }

    public function getBillingAddress()
    {
        $mageAddr = $this->getOrder()->getBillingAddress();
        return $this->getAddress($mageAddr);
    }

    public function getClientDetails()
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $httpHeader = $om->get('Magento\Framework\HTTP\Header');

        return new Model\ClientDetails(array_filter(array(
            'accept_language' => $resolver->getLocale(),
            'user_agent' => $httpHeader->getHttpUserAgent()
        ), 'strlen'));
    }

    public function getCustomer()
    {
        $customer_id = $this->getOrder()->getCustomerId();
        $customer_props = array(
            'id' => $customer_id,
            'email' => $this->getOrder()->getCustomerEmail(),
            'first_name' => $this->getOrder()->getCustomerFirstname(),
            'last_name' => $this->getOrder()->getCustomerLastname(),
            'note' => $this->getOrder()->getCustomerNote(),
            'group_name' => $this->getOrder()->getCustomerGroupId()
        );
        if ($customer_id) {
            $customer_details = $this->_customerFactory->load($customer_id);
            $customer_props['created_at'] = $this->formatDateAsIso8601($customer_details->getCreatedAt());
            $customer_props['updated_at'] = $this->formatDateAsIso8601($customer_details->getUpdatedAt());
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

    public function getCustomerSession()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectManager->get('Magento\Customer\Model\Session');
    }

    public function getLineItems()
    {
        $line_items = array();

        foreach ($this->getOrder()->getAllVisibleItems() as $key => $item) {
            $prod_type = null;

            $prod_type = null;
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

                if($product->getManufacturer()) {
                    $brand = $product->getResource()->getAttribute('manufacturer')->getFrontend()->getValue($product);
                }
            }
            $line_items[] = new Model\LineItem(array_filter(array(
                'price' => $item->getPrice(),
                'quantity' => intval($item->getQtyOrdered()),
                'title' => $item->getName(),
                'sku' => $item->getSku(),
                'product_id' => $item->getItemId(),
                'grams' => $item->getWeight(),
                'product_type' => $prod_type,
                'brand'	=> $brand,
                'category' => (isset($categories) && !empty($categories)) ? implode('|', $categories) : '',
                'sub_category' => (isset($sub_categories) && !empty($sub_categories)) ? implode('|', $sub_categories) : ''
            ), 'strlen'));
        }
        return $line_items;
    }

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
            && $paymentProcessor instanceof \Riskified\Decider\Api\Order\PaymentProcessor\Paypal
        ) {
            return new Model\PaymentDetails(array_filter(array(
                'authorization_id' => $paymentData['transaction_id'],
                'payer_email' => $paymentData['payer_email'],
                'payer_status' => $paymentData['payer_status'],
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

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        if (!isset($paymentData['credit_card_bin']) || !$paymentData['credit_card_bin']) {
            $session = $om->get('Magento\Customer\Model\Session');
            $paymentData['credit_card_bin'] = $session->getRiskifiedBin();
            $session->unsRiskifiedBin();
        }
        if (!isset($paymentData['credit_card_bin']) || !$paymentData['credit_card_bin']) {
            $coreRegistry = $om->get('Magento\Framework\Registry');
            $paymentData['credit_card_bin'] = $coreRegistry->registry('riskified_cc_bin');
        }
        if (isset($paymentData['credit_card_bin'])) {
            $paymentData['credit_card_bin'] = "XXXX-XXXX-XXXX-" . $paymentData['credit_card_bin'];
        }
    }

    public function getShippingLines()
    {
        return new Model\ShippingLine(array_filter(array(
            'price' => $this->getOrder()->getShippingAmount(),
            'title' => strip_tags($this->getOrder()->getShippingDescription()),
            'code' => $this->getOrder()->getShippingMethod()
        ), 'strlen'));
    }

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

    public function getOrderCancellation()
    {
        $orderCancellation = new Model\OrderCancellation(array_filter(array(
            'id' => $this->getOrderOrigId(),
            'cancelled_at' => $this->formatDateAsIso8601($this->getCancelledAt()),
            'cancel_reason' => 'Cancelled by merchant'
        )));
        return $orderCancellation;
    }

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
                foreach ($remotes AS $k => $val) {
                    $remotes[$k] = trim($val);
                }

                return join(',', $remotes);
            }
        }
        return $remoteIp;
    }

    public function formatDateAsIso8601($dateStr)
    {
        return ($dateStr == NULL) ? NULL : date('c', strtotime($dateStr));
    }

    public function isAdmin() {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $state =  $om->get('Magento\Framework\App\State');

        return $state->getAreaCode() === 'adminhtml';
    }
}