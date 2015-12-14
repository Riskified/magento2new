<?php
namespace Riskified\Decider\Block;

class Js extends \Magento\Framework\View\Element\Template {
    private $apiConfig;
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Riskified\Decider\Api\Config $apiConfig,
        \Magento\Framework\Module\FullModuleList $fullModuleList,
        array $data = [])
    {
        $this->validator = $context->getValidator();
        $this->resolver = $context->getResolver();
        $this->_filesystem = $context->getFilesystem();
        $this->templateEnginePool = $context->getEnginePool();
        $this->_storeManager = $context->getStoreManager();
        $this->_appState = $context->getAppState();
        $this->templateContext = $this;
        $this->pageConfig = $context->getPageConfig();
        $this->apiConfig = $apiConfig;
        $this->apiConfig = $apiConfig;
        parent::__construct($context, $data);
    }

    public function getSessionId() {
        return $this->apiConfig->getSessionId();
    }
    public function getShopDomain() {
        return $this->apiConfig->getShopDomain();
    }
    public function getConfigStatusControlActive() {
        return $this->apiConfig->getConfigStatusControlActive();
    }
    public function getExtensionVersion() {
        return $this->apiConfig->getExtensionVersion();
    }

}