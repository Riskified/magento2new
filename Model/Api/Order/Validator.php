<?php

namespace Riskified\Decider\Model\Api\Order;

use Magento\Framework\App\Config\ScopeConfigInterface;
use \Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface as ScopeInterface;
use \Riskified\Decider\Model\Api\Log;

class Validator
{
    private ScopeConfigInterface $config;
    private Log $log;
    private OrderInterface $order;

    public function __construct(ScopeConfigInterface $config, Log $log)
    {
        $this->config = $config;
        $this->log = $log;
    }

    /**
     * @param $model
     */
    public function validate($order) : bool
    {
        $this->order = $order;

        try {
            $this->validatePaymentMethod();
            $this->validateCustomerEmail();
            $this->validateProductTypes();
            $this->validateProductCategories();
        } catch (Exception $e) {
            $this->log->log($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    private function validatePaymentMethod(): void
    {
        $invalidPaymentMethods = $this->config->getValue('riskified/exclude_rules/payment_methods',
            ScopeInterface::SCOPE_STORES,
            $this->order->getStoreId()
        );

        if (!$invalidPaymentMethods) {
            return;
        }

        $invalidPaymentMethods = explode(',', $invalidPaymentMethods);

        if (in_array($this->order->getPayment()->getMethod(), $invalidPaymentMethods)) {
            throw new Exception("Order #{$this->order->getIncrementId()} is not valid to be send to Riskified - payment method is excluded.");
        }
    }

    /**
     * @throws Exception
     */
    private function validateCustomerEmail(): void
    {
        $invalidCustomerEmails = $this->config->getValue('riskified/exclude_rules/customer_email',
            ScopeInterface::SCOPE_STORES,
            $this->order->getStoreId()
        );

        if (!$invalidCustomerEmails) {
            return;
        }
        $customerEmails = explode(",", $invalidCustomerEmails);
        foreach ($customerEmails as $key => $email) {
            $customerEmails[$key] = trim($email);
        }

        if (in_array($this->order->getCustomerEmail(), $customerEmails)) {
            throw new Exception("Order #{$this->order->getIncrementId()} is not valid to be send to Riskified - customer email is excluded.");
        }
    }

    /**
     * @throws Exception
     */
    private function validateProductCategories(): void
    {
        $invalidProductCategories = $this->config->getValue('riskified/exclude_rules/category',
            ScopeInterface::SCOPE_STORES,
            $this->order->getStoreId()
        );

        if (!$invalidProductCategories) {
            return;
        }
        $invalidProductCategories = explode(',', $invalidProductCategories);

        foreach ($this->order->getAllItems() as $item) {
            $categoryIds = $item->getProduct()->getCategoryIds();
            $commonCategories = array_intersect($categoryIds, $invalidProductCategories);

            if (!empty($commonCategories)) {
                throw new Exception(
                    "Order #{$this->order->getIncrementId()} is not valid to be send to Riskified - product categories."
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    private function validateProductTypes(): void
    {
        $invalidProductTypes = $this->config->getValue('riskified/exclude_rules/product_type',
            ScopeInterface::SCOPE_STORES,
            $this->order->getStoreId()
        );

        if (!$invalidProductTypes) {
            return;
        }

        $invalidProductTypes = explode(',', $invalidProductTypes);

        foreach ($this->order->getAllItems() as $item) {
            if (in_array($item->getProduct()->getTypeId(), $invalidProductTypes)){
                throw new Exception(
                    "Order #{$this->order->getIncrementId()} is not valid to be send to Riskified - product types."
                );
            }
        }
    }
}
