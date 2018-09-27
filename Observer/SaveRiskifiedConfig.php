<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\OrderWebhook\Model;

class SaveRiskifiedConfig implements ObserverInterface
{
    /**
     * @var \Riskified\Decider\Logger\Merchant
     */
    private $logger;

    /**
     * @var \Riskified\Decider\Api\Merchant
     */
    private $apiMerchantLayer;

    /**
     * @var \Riskified\Decider\Api\Config
     */
    private $apiConfig;

    /**
     * @var \Magento\Payment\Model\Config
     */
    private $_paymentConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $storeConfig;

    /**
     * SaveRiskifiedConfig constructor.
     *
     * @param \Riskified\Decider\Logger\Merchant $logger
     * @param \Riskified\Decider\Api\Config $config
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Riskified\Decider\Api\Merchant $merchantApi
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $storeConfig
     */
    public function __construct(
        \Riskified\Decider\Logger\Merchant $logger,
        \Riskified\Decider\Api\Config $config,
        \Magento\Payment\Model\Config $paymentConfig,
        \Riskified\Decider\Api\Merchant $merchantApi,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $storeConfig
    ) {
        $this->logger = $logger;
        $this->apiConfig = $config;
        $this->_paymentConfig = $paymentConfig;
        $this->apiMerchantLayer = $merchantApi;
        $this->storeManager = $storeManager;
        $this->storeConfig = $storeConfig;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->apiConfig->isEnabled()) {
            return;
        }

        $helper = $this->apiConfig;
        $allActiveMethods = $this->_paymentConfig->getActiveMethods();
        $settings = $this->storeConfig->getValue('riskified/riskified');

        $gateWays = '';

        foreach ($allActiveMethods as $key => $value) {
            $gateWays .= $key . ",";
        }

        $extensionVersion = $helper->getExtensionVersion();

        $shopHostUrl = $this->storeManager->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $settings['gws'] = $gateWays;
        $settings['host_url'] = $shopHostUrl;
        $settings['extension_version'] = $extensionVersion;
        unset($settings['key']);
        unset($settings['domain']);

        $settingsModel = new Model\MerchantSettings(array(
            'settings' => $settings
        ));

        if ($this->apiConfig->getAuthToken() && $this->apiConfig->getShopDomain()) {
            $this->apiMerchantLayer->update($settingsModel);
        }
    }
}
