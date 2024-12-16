<?php

namespace Riskified\Decider\Model\Api;

use Riskified\Common\Exception\BaseException;
use Riskified\Decider\Model\Logger\Merchant as MerchantLogger;
use Magento\Framework\Message\ManagerInterface;
use Riskified\OrderWebhook\Exception\UnsuccessfulActionException;
use Riskified\OrderWebhook\Exception\CurlException;

class Merchant
{
    /**
     * @var Api
     */
    private Api $_api;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $_messageManager;

    /**
     * @var MerchantLogger
     */
    private MerchantLogger $logger;

    /**
     * Merchant constructor.
     *
     * @param Api $api
     * @param MerchantLogger $logger
     * @param ManagerInterface $messageManager
     */
    public function __construct(Api $api, MerchantLogger $logger, ManagerInterface $messageManager)
    {
        $this->_api = $api;
        $this->_messageManager = $messageManager;
        $this->logger = $logger;

        $this->_api->initSdk();
    }

    /**
     * @param $settings
     *
     * @return object
     * @throws CurlException
     * @throws UnsuccessfulActionException
     * @throws BaseException
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
