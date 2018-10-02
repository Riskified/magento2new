<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Riskified\OrderWebhook\Model;
use Riskified\Decider\Model\Logger\Merchant as MerchantLogger;
use Riskified\Decider\Model\Api\Config as ApiConfig;
use Magento\Payment\Model\Config as PaymentConfig;
use Riskified\Decider\Model\Api\Merchant as ApiMerchant;

class SaveRiskifiedConfig implements ObserverInterface
{
    /**
     * @var MerchantLogger
     */
    private $logger;

    /**
     * @var ApiMerchant
     */
    private $apiMerchantLayer;

    /**
     * @var ApiConfig
     */
    private $apiConfig;

    /**
     * @var PaymentConfig
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
     * @param MerchantLogger $logger
     * @param ApiConfig $config
     * @param PaymentConfig $paymentConfig
     * @param ApiMerchant $merchantApi
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $storeConfig
     */
    public function __construct(
        MerchantLogger $logger,
        ApiConfig $config,
        PaymentConfig $paymentConfig,
        ApiMerchant $merchantApi,
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
