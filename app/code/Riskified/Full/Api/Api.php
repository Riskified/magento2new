<?php
namespace Riskified\Full\Api;
use Riskified\Common\Riskified;
use Riskified\Common\Signature;
use Riskified\Common\Validations;
use Riskified\OrderWebhook\Model;
use Riskified\OrderWebhook\Transport;
use Riskified\DecisionNotification\Model\Notification as DecisionNotification;

class Api
{
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_SUBMIT = 'submit';
    const ACTION_CANCEL = 'cancel';

    private $version;
    private $_apiConfig;

    public function __construct(Config $apiConfig) {
        $this->_apiConfig           = $apiConfig;
    }

    public function initSdk() {
        $authToken = $this->_apiConfig->getAuthToken();
        $env = constant($this->_apiConfig->getConfigEnv());
        $shopDomain = $this->_apiConfig->getShopDomain();
        $this->version = $this->_apiConfig->getExtensionVersion();

        Riskified::init($shopDomain, $authToken, $env, Validations::SKIP);
    }

    public function getTransport() {
        $transport = new Transport\CurlTransport(new Signature\HttpDataSignature());
        $transport->timeout = 15;
        return $transport;
    }

    protected function getHeaders() {
        return array('headers' => array('X_RISKIFIED_VERSION:' . $this->version));
    }

    public function parseRequest($request) {
        $header_name = Signature\HttpDataSignature::HMAC_HEADER_NAME;
        $headers = array($header_name => $request->getHeader($header_name));
        $body = $request->getRawBody();
        return new DecisionNotification(new Signature\HttpDataSignature(), $headers, $body);
    }
}