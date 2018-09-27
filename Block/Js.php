<?php

namespace Riskified\Decider\Block;

use Magento\Framework\View\Element\Template\Context;
use Riskified\Decider\Api\Config;
use Magento\Framework\View\Element\Template;

class Js extends Template
{
    /**
     * @var Config
     */
    private $apiConfig;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $session;

    /**
     * @var bool
     */
    protected $_isScopePrivate = false;

    /**
     * Js constructor.
     *
     * @param Context $context
     * @param Config $apiConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $apiConfig,
        array $data = []
    ) {
        $this->apiConfig = $apiConfig;
        $this->session = $context->getSession();

        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->session->getSessionId();
    }

    /**
     * @return bool|mixed
     */
    public function isEnabled()
    {
        return $this->apiConfig->isEnabled();
    }

    /**
     * @return mixed
     */
    public function getShopDomain()
    {
        return $this->apiConfig->getShopDomain();
    }

    /**
     * @return bool|mixed
     */
    public function getConfigStatusControlActive()
    {
        return $this->apiConfig->getConfigStatusControlActive();
    }

    /**
     * @return mixed|string
     */
    public function getExtensionVersion()
    {
        return $this->apiConfig->getExtensionVersion();
    }

    /**
     * @return string
     */
    public function getConfigBeaconUrl()
    {
        return $this->apiConfig->getConfigBeaconUrl();
    }
}
