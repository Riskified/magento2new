<?php
namespace Riskified\Decider\Api;
use Riskified\Common\Signature;
use Riskified\OrderWebhook\Model;
use Riskified\OrderWebhook\Transport;

class Merchant
{
    private $_api;
    private $_orderHelper;
    private $_context;
    private $_eventManager;
    private $_messageManager;
    private $_backendAuthSession;
    private $logger;

    public function __construct(
        Api $api,
        Order\Helper $orderHelper,
        Config $apiConfig,
        \Riskified\Decider\Logger\Merchant $logger,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->_api                 = $api;
        $this->_orderHelper         = $orderHelper;
        $this->_apiConfig           = $apiConfig;
        $this->_context             = $context;
        $this->_eventManager        = $context->getEventManager();
        $this->_backendAuthSession  = $backendAuthSession;
        $this->_messageManager      = $messageManager;
        $this->logger               = $logger;

        $this->_api->initSdk();
    }
    public function update($settings) {
        $transport = $this->_api->getTransport();
        $this->logger->addInfo('Merchant::Update');
        try {
            $response = $transport->updateMerchantSettings($settings);
            $this->logger->addInfo(__('Merchant Settings posted successfully'));
        } catch(\Riskified\OrderWebhook\Exception\UnsuccessfulActionException $uae) {
            if ($uae->statusCode == '401') {
                $this->logger->addCritical($uae);
                $this->_messageManager->addError(__('Make sure you have the correct Auth token as it appears in Riskified advanced settings.'));
            }
            throw $uae;
        } catch(\Riskified\OrderWebhook\Exception\CurlException $curlException) {
            $this->logger->addCritical($curlException);
            $this->_messageManager->addError(__('Riskified extension: %s', $curlException->getMessage()) );
            throw $curlException;
        }
        catch (\Exception $e) {
            $this->logger->addCritical($e);
            $this->_messageManager->addError('Riskified extension: ' . $e->getMessage());
            throw $e;
        }
        return $response;
    }
}