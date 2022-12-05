<?php

namespace Riskified\Decider\Model\Api;

use Riskified\Decider\Model\Logger\Merchant as MerchantLogger;
use Magento\Framework\App\Helper\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Message\ManagerInterface;
use Riskified\OrderWebhook\Exception\UnsuccessfulActionException;
use Riskified\OrderWebhook\Exception\CurlException;

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
     * @var Context
     */
    private $_context;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $_eventManager;

    /**
     * @var ManagerInterface
     */
    private $_messageManager;

    /**
     * @var AuthSession
     */
    private $_backendAuthSession;

    /**
     * @var MerchantLogger
     */
    private $logger;

    /**
     * Merchant constructor.
     *
     * @param Api $api
     * @param Order\Helper $orderHelper
     * @param Config $apiConfig
     * @param MerchantLogger $logger
     * @param Context $context
     * @param AuthSession $backendAuthSession
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Api $api,
        Order\Helper $orderHelper,
        Config $apiConfig,
        MerchantLogger $logger,
        Context $context,
        AuthSession $backendAuthSession,
        ManagerInterface $messageManager
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
     *
     * @return object
     *
     * @throws \Exception
     * @throws CurlException
     * @throws UnsuccessfulActionException
     */
    public function update($settings)
    {
        $transport = $this->_api->getTransport();
        $this->logger->info('Merchant::Update');
        try {
            $response = $transport->updateMerchantSettings($settings);
            $this->logger->info(__('Merchant Settings posted successfully'));
        } catch (UnsuccessfulActionException $uae) {
            if ($uae->statusCode == '401') {
                $this->logger->critical($uae);
                $this->_messageManager->addError(
                    __('Make sure you have the correct Auth token as it appears in Riskified advanced settings.')
                );
            }
            throw $uae;
        } catch (CurlException $curlException) {
            $this->logger->critical($curlException);
            $this->_messageManager->addError(__('Riskified extension: %s', $curlException->getMessage()));
            throw $curlException;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->_messageManager->addError('Riskified extension: ' . $e->getMessage());
            throw $e;
        }
        return $response;
    }
}
