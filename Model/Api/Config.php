<?php
namespace Riskified\Decider\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Store\Model\ScopeInterface as ScopeInterface;

class Config
{
    private $version;
    private $_scopeConfig;
    private $cookieManager;
    private $fullModuleList;
    private $checkoutSession;
    private $store;

    const BEACON_URL = 'beacon.riskified.com';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Module\FullModuleList $fullModuleList,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->_scopeConfig     = $scopeConfig;
        $this->cookieManager    = $cookieManager;
        $this->fullModuleList   = $fullModuleList;
        $this->checkoutSession  = $checkoutSession;
    }

    public function isEnabled()
    {
        return $this->_scopeConfig->getValue('riskified/riskified_general/enabled');
    }

    public function getHeaders()
    {
        return [
            'headers' => [
                'X_RISKIFIED_VERSION:' . $this->version
            ]
        ];
    }

    public function getAuthToken()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/key',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getConfigStatusControlActive()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/order_status_sync',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getConfigEnv()
    {
        return '\Riskified\Common\Env::' . $this->_scopeConfig->getValue(
                'riskified/riskified/env',
                ScopeInterface::SCOPE_STORES,
                $this->getStore()
            );
    }

    public function getSessionId()
    {
        return $this->checkoutSession->getQuoteId();
    }

    public function getConfigEnableAutoInvoice()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_enabled',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getConfigAutoInvoiceCaptureCase()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getConfigBeaconUrl()
    {
        return self::BEACON_URL;
    }

    public function getShopDomain()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/domain',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getExtensionVersion()
    {
        $moduleConfig = $this->fullModuleList->getOne('Riskified_Decider');
        return $moduleConfig['setup_version'];
    }

    public function getDeclinedState()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/declined_state',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getDeclinedStatus()
    {
        $state = $this->getDeclinedState();
        return $this->_scopeConfig->getValue(
            'riskified/riskified/declined_status_' . $state,
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getApprovedState()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/approved_state',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getApprovedStatus()
    {
        $state = $this->getApprovedState();
        return $this->_scopeConfig->getValue(
            'riskified/riskified/approved_status_' . $state,
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function isLoggingEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/debug_logs',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function isAutoInvoiceEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_enabled',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getInvoiceCaptureCase()
    {
        $captureCase = $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
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

    public function getCaptureCase()
    {
        $captureCase = $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
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
     * When scopeId is not defined checks if Decline Notification is enabled for Default Store View.
     * @return bool
     */
    public function isDeclineNotificationEnabled($scopeId = 0)
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/decline_notification/enabled',
            ScopeInterface::SCOPE_STORES,
            $scopeId
        );
    }

    public function getDeclineNotificationSender()
    {
        return $this->_scopeConfig->getValue(
            'riskified/decline_notification/email_identity',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getDeclineNotificationSenderEmail()
    {
        return $this->_scopeConfig->getValue(
            'trans_email/ident_' . $this->getDeclineNotificationSender() . '/email',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    public function getDeclineNotificationSenderName()
    {
        return $this->_scopeConfig->getValue(
            'trans_email/ident_' . $this->getDeclineNotificationSender() . '/name',
            ScopeInterface::SCOPE_STORES,
            $this->getStore()
        );
    }

    /**
     * If scopeId is not defined returns Decline Notification Subject for Default Store View.
     * @param int $scopeId
     * @return mixed
     */
    public function getDeclineNotificationSubject($scopeId = 0)
    {
        return $this->_scopeConfig->getValue(
            'riskified/decline_notification/title',
            ScopeInterface::SCOPE_STORES,
            $scopeId
        );
    }

    /**
     * If scopeId is not defined returns Decline Notification Content for Default Store View.
     * @param int $scopeId
     * @return mixed
     */
    public function getDeclineNotificationContent($scopeId = 0)
    {
        return $this->_scopeConfig->getValue(
            'riskified/decline_notification/content',
            ScopeInterface::SCOPE_STORES,
            $scopeId
        );
    }

    /**
     * Sets store id.
     * @param $id
     */
    public function setStore($id)
    {
        $this->store = $id;
    }

    /**
     * Returns store id. If not defined returns default store value.
     * @return string
     */
    public function getStore()
    {
        return (!is_null($this->store)) ? $this->store : null;
    }
}
