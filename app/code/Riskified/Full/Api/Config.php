<?php
namespace Riskified\Full\Api;

class Config
{
    private $version;
    private $_scopeConfig;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig) {
        $this->_scopeConfig = $scopeConfig;
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

    public function getConfigEnableAutoInvoice(){
        return $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_enabled');
    }

    public function getConfigAutoInvoiceCaptureCase(){
        return $this->_scopeConfig->getValue('riskified/riskified/auto_invoice_capture_case');
    }

    public function getConfigBeaconUrl(){
        return $this->_scopeConfig->getValue('riskified/riskified/beaconurl');
    }

    public function getShopDomain(){
        return $this->_scopeConfig->getValue('riskified/riskified/domain');
    }

    public function getExtensionVersion(){
        return "1.1.1";
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
}