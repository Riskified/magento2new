<?php
namespace Riskified\Decider\Api;

use \Magento\Store\Model\ScopeInterface as ScopeInterface;

class Config
{
    private $version;
    private $_scopeConfig;
    private $cookieManager;
    private $fullModuleList;
    private $checkoutSession;

    const BEACON_URL = 'beacon.riskified.com';

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
            ScopeInterface::SCOPE_STORES
        );
    }

    public function getConfigStatusControlActive()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/order_status_sync',
            ScopeInterface::SCOPE_STORES
        );
    }

    public function getConfigEnv()
    {
        return '\Riskified\Common\Env::' . $this->_scopeConfig->getValue(
                'riskified/riskified/env',
                ScopeInterface::SCOPE_STORES
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
            ScopeInterface::SCOPE_STORES
        );
    }

    public function getConfigAutoInvoiceCaptureCase()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_capture_case',
            ScopeInterface::SCOPE_STORES
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
            ScopeInterface::SCOPE_STORES
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
            ScopeInterface::SCOPE_STORES
        );
    }

    public function getDeclinedStatus()
    {
        $state = $this->getDeclinedState();
        return $this->_scopeConfig->getValue(
            'riskified/riskified/declined_status_' . $state,
            ScopeInterface::SCOPE_STORES
        );
    }

    public function getApprovedState()
    {
        return $this->_scopeConfig->getValue(
            'riskified/riskified/approved_state',
            ScopeInterface::SCOPE_STORES
        );
    }

    public function getApprovedStatus()
    {
        $state = $this->getApprovedState();
        return $this->_scopeConfig->getValue(
            'riskified/riskified/approved_status_' . $state,
            ScopeInterface::SCOPE_STORES
        );
    }

    public function isLoggingEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/debug_logs',
            ScopeInterface::SCOPE_STORES
        );
    }

    public function isAutoInvoiceEnabled()
    {
        return (bool)$this->_scopeConfig->getValue(
            'riskified/riskified/auto_invoice_enabled',
            ScopeInterface::SCOPE_STORES
        );
    }

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
}
