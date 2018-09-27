<?php

namespace Riskified\Decider\Api;

class Merchant
{
    /**
     * @var Api
     */
    private $_api;

    /**
     * @var Order\Helper
     */
    private $_orderHelper;

    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    private $_context;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $_eventManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $_messageManager;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $_backendAuthSession;

    /**
     * @var \Riskified\Decider\Logger\Merchant
     */
    private $logger;

    /**
     * Merchant constructor.
     *
     * @param Api $api
     * @param Order\Helper $orderHelper
     * @param Config $apiConfig
     * @param \Riskified\Decider\Logger\Merchant $logger
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Backend\Model\Auth\Session $backendAuthSession
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        Api $api,
        Order\Helper $orderHelper,
        Config $apiConfig,
        \Riskified\Decider\Logger\Merchant $logger,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->_api = $api;
        $this->_orderHelper = $orderHelper;
        $this->_apiConfig = $apiConfig;
        $this->_context = $context;
        $this->_eventManager = $context->getEventManager();
        $this->_backendAuthSession = $backendAuthSession;
        $this->_messageManager = $messageManager;
        $this->logger = $logger;

        $this->_api->initSdk();
    }

    /**
     * @param $settings
     * @return object
     * @throws \Exception
     * @throws \Riskified\OrderWebhook\Exception\CurlException
     * @throws \Riskified\OrderWebhook\Exception\UnsuccessfulActionException
     */
    public function update($settings)
    {
        $transport = $this->_api->getTransport();
        $this->logger->addInfo('Merchant::Update');
        try {
            $response = $transport->updateMerchantSettings($settings);
            $this->logger->addInfo(__('Merchant Settings posted successfully'));
        } catch (\Riskified\OrderWebhook\Exception\UnsuccessfulActionException $uae) {
            if ($uae->statusCode == '401') {
                $this->logger->addCritical($uae);
                $this->_messageManager->addError(__('Make sure you have the correct Auth token as it appears in Riskified advanced settings.'));
            }
            throw $uae;
        } catch (\Riskified\OrderWebhook\Exception\CurlException $curlException) {
            $this->logger->addCritical($curlException);
            $this->_messageManager->addError(__('Riskified extension: %s', $curlException->getMessage()));
            throw $curlException;
        } catch (\Exception $e) {
            $this->logger->addCritical($e);
            $this->_messageManager->addError('Riskified extension: ' . $e->getMessage());
            throw $e;
        }
        return $response;
    }
}
