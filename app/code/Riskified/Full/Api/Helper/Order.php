<?php
namespace Riskified\Full\Api\Helper;
use Riskified\OrderWebhook\Model;

class Order
{
    private $_order;
    private $_apiLogger;
    private $_logger;
    private $_apiConfig;
    private $_messageManager;
    private $_customerFactory;
    private $_orderFactory;

    public function __construct(
        \Magento\Framework\Logger\Monolog $logger,
        \Riskified\Full\Api\Config $apiConfig,
        \Riskified\Full\Logger\Order $apiLogger,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Customer\Model\Customer $customerFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderFactory
    )
    {
        $this->_logger = $logger;
        $this->_messageManager = $messageManager;
        $this->_apiConfig = $apiConfig;
        $this->_apiLogger = $apiLogger;
        $this->_customerFactory = $customerFactory;
        $this->_orderFactory = $orderFactory;
    }

    public function setOrder($model)
    {
        $this->_order = $model;
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function getOrderOrigId() {
        if(!$this->getOrder()) {
            return null;
        }
        return $this->getOrder()->getId() . '_' . $this->getOrder()->getIncrementId();
    }

    public function getDiscountCodes() {
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

    public function getClientDetails() {
        return new Model\ClientDetails(array_filter(array(
//            'accept_language' => Mage::app()->getLocale()->getLocaleCode(),
//            'user_agent' => Mage::helper('core/http')->getHttpUserAgent()
        ),'strlen'));
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

    public function getCustomerSession() {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectManager->get('Magento\Customer\Model\Session');
    }

    public function getLineItems()
    {
        $line_items = array();
        foreach ($this->getOrder()->getAllVisibleItems() as $key => $val) {
            $prod_type = null;
            if ($val->getProduct()) {
                $prod_type = $val->getProduct()->getTypeId();
            }
            $line_items[] = new Model\LineItem(array_filter(array(
                'price' => $val->getPrice(),
                'quantity' => intval($val->getQtyOrdered()),
                'title' => $val->getName(),
                'sku' => $val->getSku(),
                'product_id' => $val->getItemId(),
                'grams' => $val->getWeight(),
                'product_type' => $prod_type
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
        $addrArray = array_filter(array(
            'first_name' => $address->getFirstname(),
            'last_name' => $address->getLastname(),
            'name' => $address->getFirstname() . " " . $address->getLastname(),
            'company' => $address->getCompany(),
            'address1' => $address_1,
            'address2' => $address_2,
            'city' => $address->getCity(),
            'country_code' => $address->getCountryId(),
//            'country' => Mage::getModel('directory/country')->load($address->getCountryId())->getName(),
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
            $this->_apiLogger->payment();
        }
        $transactionId = $payment->getTransactionId();
        $gateway_name = $payment->getMethod();
        try {
            switch ($gateway_name) {
                case 'authorizenet':
                    $authorize_data = $payment->getAdditionalInformation('authorize_cards');
                    if ($authorize_data && is_array($authorize_data)) {
                        $cards_data = array_values($authorize_data);
                        if ($cards_data && $cards_data[0]) {
                            $card_data = $cards_data[0];
                            if (isset($card_data['cc_last4'])) {
                                $credit_card_number = $card_data['cc_last4'];
                            }
                            if (isset($card_data['cc_type'])) {
                                $credit_card_company = $card_data['cc_type'];
                            }
                            if (isset($card_data['cc_avs_result_code'])) {
                                $avs_result_code = $card_data['cc_avs_result_code'];
                            }
                            if (isset($card_data['cc_response_code'])) {
                                $cvv_result_code = $card_data['cc_response_code'];
                            }
                        }
                    }
                    break;
                case 'authnetcim':
                    $avs_result_code = $payment->getAdditionalInformation('avs_result_code');
                    $cvv_result_code = $payment->getAdditionalInformation('card_code_response_code');
                    break;
                case 'optimal_hosted':
                    try {
                        $optimalTransaction = unserialize($payment->getAdditionalInformation('transaction'));
                        $cvv_result_code = $optimalTransaction->cvdVerification;
                        $houseVerification = $optimalTransaction->houseNumberVerification;
                        $zipVerification = $optimalTransaction->zipVerification;
                        $avs_result_code = $houseVerification . ',' . $zipVerification;
                    } catch (\Exception $e) {
                    }
                    break;
                case 'paypal_express':
                case 'paypaluk_express':
                case 'paypal_standard':
                    $payer_email = $payment->getAdditionalInformation('paypal_payer_email');
                    $payer_status = $payment->getAdditionalInformation('paypal_payer_status');
                    $payer_address_status = $payment->getAdditionalInformation('paypal_address_status');
                    $protection_eligibility = $payment->getAdditionalInformation('paypal_protection_eligibility');
                    $payment_status = $payment->getAdditionalInformation('paypal_payment_status');
                    $pending_reason = $payment->getAdditionalInformation('paypal_pending_reason');
                    return new Model\PaymentDetails(array_filter(array(
                        'authorization_id' => $transactionId,
                        'payer_email' => $payer_email,
                        'payer_status' => $payer_status,
                        'payer_address_status' => $payer_address_status,
                        'protection_eligibility' => $protection_eligibility,
                        'payment_status' => $payment_status,
                        'pending_reason' => $pending_reason
                    ), 'strlen'));
                case 'paypal_direct':
                case 'paypaluk_direct':
                    $avs_result_code = $payment->getAdditionalInformation('paypal_avs_code');
                    $cvv_result_code = $payment->getAdditionalInformation('paypal_cvv2_match');
                    $credit_card_number = $payment->getCcLast4();
                    $credit_card_company = $payment->getCcType();
                    break;
                case 'sagepaydirectpro':
                case 'sage_pay_form':
                case 'sagepayserver':
                    $sage = $this->getOrder()->getSagepayInfo();
                    if ($sage) {
                        $avs_result_code = $sage->getData('address_result');
                        $cvv_result_code = $sage->getData('cv2result');
                        $credit_card_number = $sage->getData('last_four_digits');
                        $credit_card_company = $sage->getData('card_type');
                    } else {
                    }
                    break;
                case 'transarmor':
                    $avs_result_code = $payment->getAdditionalInformation('avs_response');
                    $cvv_result_code = $payment->getAdditionalInformation('cvv2_response');
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
        }
        if (!isset($cvv_result_code)) {
            $cvv_result_code = $payment->getCcCidStatus();
        }
        if (!isset($credit_card_number)) {
            $credit_card_number = $payment->getCcLast4();
        }
        if (!isset($credit_card_company)) {
            $credit_card_company = $payment->getCcType();
        }
        if (!isset($avs_result_code)) {
            $avs_result_code = $payment->getCcAvsStatus();
        }
        if (isset($credit_card_number)) {
            $credit_card_number = "XXXX-XXXX-XXXX-" . $credit_card_number;
        }
        $credit_card_bin = $payment->getAdditionalInformation('riskified_cc_bin');
        return new Model\PaymentDetails(array_filter(array(
            'authorization_id' => $transactionId,
            'avs_result_code' => $avs_result_code,
            'cvv_result_code' => $cvv_result_code,
            'credit_card_number' => $credit_card_number,
            'credit_card_company' => $credit_card_company,
            'credit_card_bin' => $credit_card_bin
        ), 'strlen'));
    }

    public function getShippingLines() {
        return new Model\ShippingLine(array_filter(array(
            'price' => $this->getOrder()->getShippingAmount(),
            'title' => $this->getOrder()->getShippingDescription(),
            'code' => $this->getOrder()->getShippingMethod()
        ),'strlen'));
    }

    public function getCancelledAt() {
        $commentCollection = $this->getOrder()->getStatusHistoryCollection();
        foreach ($commentCollection as $comment) {
            if ($comment->getStatus() == \Magento\Sales\Model\Order::STATE_CANCELED) {
                return 'now';
            }
        }
        return null;
    }

    public function getRemoteIp() {
        $this->_apiLogger->debug("remote ip: " . $this->getOrder()->getRemoteIp() . ", x-forwarded-ip: " . $this->getOrder()->getXForwardedFor());
        $forwardedIp = $this->getOrder()->getXForwardedFor();
        $forwardeds = preg_split("/,/",$forwardedIp, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($forwardeds)) {
            return trim($forwardeds[0]);
        }
        $remoteIp = $this->getOrder()->getRemoteIp();
        $remotes = preg_split("/,/",$remoteIp, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($remotes)) {
            return trim($remotes[0]);
        }
        return $remoteIp;
    }

    public function formatDateAsIso8601($dateStr)
    {
        return ($dateStr == NULL) ? NULL : date('c', strtotime($dateStr));
    }
}