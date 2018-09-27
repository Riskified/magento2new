<?php

namespace Riskified\Decider\Api;

use \Magento\Store\Model\ScopeInterface as ScopeInterface;

class Config
{
    /**
     * @var string
     */
    private $version;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $_scopeConfig;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var \Magento\Framework\Module\FullModuleList
     */
    private $fullModuleList;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var string
     */
    const BEACON_URL = 'beacon.riskified.com';

    /**
     * Config constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Module\FullModuleList $fullModuleList
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Module\FullModuleList $fullModuleList,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->_scopeConfig     = $scopeConfig;
        $this->cookieManager    = $cookieManager;
        $this->fullModuleList   = $fullModuleList;
        $this->checkoutSession  = $checkoutSession;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return (bool)$this->_scopeConfig->getValue('riskified/riskified_general/enabled');
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return [
            'headers' => [
                'X_RISKIFIED_VERSION:' . $this->version
            ]
        ];
    }

    /**
     * @return string
     */
    public function getAuthToken()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/key',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return bool
     */
    public function getConfigStatusControlActive()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/order_status_sync',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getConfigEnv()
    {
        return '\Riskified\Common\Env::'
            . $this->_scopeConfig->getValue(
                'riskified/riskified/env',
                ScopeInterface::SCOPE_STORES
            );
    }

    /**
     * @return int
     */
    public function getSessionId()
    {
        return $this->checkoutSession->getQuoteId();
    }

    /**
     * @return bool
     */
    public function getConfigEnableAutoInvoice()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_enabled',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getConfigAutoInvoiceCaptureCase()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getConfigBeaconUrl()
    {
        return self::BEACON_URL;
    }

    /**
     * @return mixed
     */
    public function getShopDomain()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/domain',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getExtensionVersion()
    {
        $moduleConfig = $this->fullModuleList->getOne('Riskified_Decider');
        return $moduleConfig['setup_version'];
    }

    /**
     * @return string
     */
    public function getDeclinedState()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/declined_state',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getDeclinedStatus()
    {
        $state = $this->getDeclinedState();
        return $this->_scopeConfig->getValue(
            'riskified/riskified/declined_status_' . $state,
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getApprovedState()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/approved_state',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getApprovedStatus()
    {
        $state = $this->getApprovedState();
        return $this->_scopeConfig->getValue(
            'riskified/riskified/approved_status_' . $state,
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return bool
     */
    public function isLoggingEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/debug_logs',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return bool
     */
    public function isAutoInvoiceEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_enabled',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return mixed|string
     */
    public function getInvoiceCaptureCase()
    {
        $captureCase = $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES
        );

        $availableStatuses = [
            \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE,
            \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE
        ];

        if (!in_array($captureCase, $availableStatuses)) {
            $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
        }

        return $captureCase;
    }

    /**
     * @return mixed|string
     */
    public function getCaptureCase()
    {
        $captureCase = $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES
        );

        $avialableStatuses =  [
            \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE,
            \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE
        ];

        if (!in_array($captureCase, $avialableStatuses)) {
            $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
        }

        return $captureCase;
    }

    /**
     * @return bool
     */
    public function isDeclineNotificationEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/decline_notification/enabled',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getDeclineNotificationSender()
    {
        return $this->_scopeConfig->getValue(
            'riskified/decline_notification/email_identity',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getDeclineNotificationSenderEmail()
    {
        return $this->_scopeConfig->getValue(
            'trans_email/ident_' . $this->getDeclineNotificationSender() . '/email',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getDeclineNotificationSenderName()
    {
        return $this->_scopeConfig->getValue(
            'trans_email/ident_' . $this->getDeclineNotificationSender() . '/name',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getDeclineNotificationSubject()
    {
        return $this->_scopeConfig->getValue(
            'riskified/decline_notification/title',
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @return string
     */
    public function getDeclineNotificationContent()
    {
        return $this->_scopeConfig->getValue(
            'riskified/decline_notification/content',
            ScopeInterface::SCOPE_STORES
        );
    }
}
