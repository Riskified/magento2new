<?php
namespace Riskified\Decider\Api;

class Config
{
    private $version;
    private $_scopeConfig;
    private $cookieManager;
    private $fullModuleList;

    const BEACON_URL = 'beacon.riskified.com';

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Module\FullModuleList $fullModuleList
    ) {
        $this->_scopeConfig     = $scopeConfig;
        $this->cookieManager    = $cookieManager;
        $this->fullModuleList   = $fullModuleList;
    }

    public function isEnabled() {
        return $this->_scopeConfig->getValue('riskified/riskified_general/enabled');
    }

    protected function getHeaders() {
        return array('headers' => array('X_RISKIFIED_VERSION:'.$this->version));
    }

    public function getAuthToken(){
        return $this->_scopeConfig->getValue('riskified/riskified/key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getConfigStatusControlActive(){
        return $this->_scopeConfig->getValue('riskified/riskified/order_status_sync');
    }

    public function getConfigEnv(){
        return '\Riskified\Common\Env::' . $this->_scopeConfig->getValue('riskified/riskified/env');
    }

    public function getSessionId(){

        return $this->cookieManager->getCookie('rCookie');
    }

    public function getConfigEnableAutoInvoice(){
        return $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_enabled');
    }

    public function getConfigAutoInvoiceCaptureCase(){
        return $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_capture_case');
    }

    public function getConfigBeaconUrl(){
        return self::BEACON_URL;
    }

    public function getShopDomain(){
        return $this->_scopeConfig->getValue('riskified/riskified/domain');
    }

    public function getExtensionVersion(){
        $moduleConfig = $this->fullModuleList->getOne('Riskified_Decider');
        return $moduleConfig['setup_version'];
    }

    public function getDeclinedState() {
        return $this->_scopeConfig->getValue('riskified/riskified/declined_state');
    }
    public function getDeclinedStatus() {
        $state = $this->getDeclinedState();
        return $this->_scopeConfig->getValue('riskified/riskified/declined_status_'.$state);
    }
    public function getApprovedState() {
        return $this->_scopeConfig->getValue('riskified/riskified/approved_state');
    }
    public function getApprovedStatus() {
        $state = $this->getApprovedState();
        return $this->_scopeConfig->getValue('riskified/riskified/approved_status_'.$state);
    }
    public function isLoggingEnabled() {
        return (bool) $this->_scopeConfig->getValue('riskified/riskified/debug_logs');
    }
    public function isAutoInvoiceEnabled() {
        return (bool) $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_enabled');
    }
    public function getInvoiceCaptureCase() {
        $case = $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_capture_case');
        if (!in_array($case, array(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE, \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE))) {
            $case = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
        }
        return $case;
    }
    public function getCaptureCase() {
        $case = $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_capture_case');
        if (!in_array($case, array(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE, \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE))) {
            $case = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
        }
        return $case;
    }
}