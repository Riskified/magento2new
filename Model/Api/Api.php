<?php

namespace Riskified\Decider\Model\Api;

use Riskified\Common\Riskified;
use Riskified\Common\Signature;
use Riskified\Common\Validations;
use Riskified\OrderWebhook\Transport\CurlTransport;
use Riskified\DecisionNotification\Model\Notification as DecisionNotification;

class Api
{
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_SUBMIT = 'submit';
    const ACTION_CANCEL = 'cancel';
    const ACTION_REFUND = 'refund';
    const ACTION_FULFILL = 'fulfill';

    /**
     * @var string
     */
    private $version;

    /**
     * @var Config
     */
    private $_apiConfig;

    /**
     * Api constructor.
     *
     * @param Config $apiConfig
     */
    public function __construct(Config $apiConfig)
    {
        $this->_apiConfig = $apiConfig;
    }

    /**
     * Init Sdk.
     */
    public function initSdk($order = null)
    {
        $storeId = (!is_null($order)) ? $order->getStore() : null;
        $this->_apiConfig->setStore($storeId);
        $authToken = $this->_apiConfig->getAuthToken();
        $env = constant($this->_apiConfig->getConfigEnv());
        $shopDomain = $this->_apiConfig->getShopDomain();
        $this->version = $this->_apiConfig->getExtensionVersion();

        Riskified::init($shopDomain, $authToken, $env, Validations::SKIP);
    }

    /**
     * @return CurlTransport
     */
    public function getTransport()
    {
        $transport = new CurlTransport(new Signature\HttpDataSignature());
        $transport->timeout = 15;
        return $transport;
    }

    /**
     * @return array
     */
    protected function getHeaders()
    {
        return [
            'headers' => [
                'X_RISKIFIED_VERSION:' . $this->version
            ]
        ];
    }

    /**
     * @param $request
     *
     * @return DecisionNotification
     */
    public function parseRequest($request)
    {
        $header_name = Signature\HttpDataSignature::HMAC_HEADER_NAME;
        $headers = [
            $header_name => $request->getHeader($header_name)
        ];
        $body = $request->getContent();
        return new DecisionNotification(new Signature\HttpDataSignature(), $headers, $body);
    }
}
