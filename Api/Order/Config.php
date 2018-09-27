<?php

namespace Riskified\Decider\Api\Order;

class Config
{
    /**
     * @var \Riskified\Decider\Api\Config
     */
    private $apiConfig;

    /**
     * @var \Riskified\Decider\Api\Log
     */
    private $apiLogger;

    /**
     * @var \Magento\Sales\Model\Order\ConfigFactory
     */
    private $configFactory;

    /**
     * Config constructor.
     *
     * @param \Riskified\Decider\Api\Config $apiConfig
     * @param \Riskified\Decider\Api\Log $apiLogger
     * @param \Magento\Sales\Model\Order\ConfigFactory $configFactory
     */
    public function __construct(
        \Riskified\Decider\Api\Config $apiConfig,
        \Riskified\Decider\Api\Log $apiLogger,
        \Magento\Sales\Model\Order\ConfigFactory $configFactory
    ) {
        $this->apiConfig = $apiConfig;
        $this->apiLogger = $apiLogger;
        $this->configFactory = $configFactory;
    }

    /**
     * @return string
     */
    public function getOnHoldStatusCode()
    {
        return 'riskified_holded';
    }

    /**
     * @return string
     */
    public function getOnHoldStatusLabel()
    {
        return 'Under Review (Riskified)';
    }

    /**
     * @return string
     */
    public function getTransportErrorStatusCode()
    {
        return 'riskified_trans_error';
    }

    /**
     * @return string
     */
    public function getTransportErrorStatusLabel()
    {
        return 'Transport Error (Riskified)';
    }

    /**
     * @return string
     */
    public function getRiskifiedDeclinedStatusCode()
    {
        return 'riskified_declined';
    }

    /**
     * @return string
     */
    public function getRiskifiedDeclinedStatusLabel()
    {
        return 'Declined (Riskified)';
    }

    /**
     * @return string
     */
    public function getRiskifiedApprovedStatusCode()
    {
        return 'riskified_approved';
    }

    /**
     * @return string
     */
    public function getRiskifiedApprovedStatusLabel()
    {
        return 'Approved (Riskified)';
    }

    /**
     * @return mixed|string
     */
    public function getSelectedApprovedState()
    {
        $state = $this->apiConfig->getApprovedState();
        if (!in_array($state, array(\Magento\Sales\Model\Order::STATE_PROCESSING, \Magento\Sales\Model\Order::STATE_HOLDED))) {
            $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
        }

        return $state;
    }

    /**
     * @return mixed|string
     */
    public function getSelectedApprovedStatus()
    {
        $status = $this->apiConfig->getApprovedStatus();
        $orderConfig = $this->configFactory->create();

        $allowedStatuses = $orderConfig->getStateStatuses($this->getSelectedApprovedState());
        if (!in_array($status, array_keys($allowedStatuses))) {
            $status = $this->getRiskifiedApprovedStatusCode();
            $this->apiLogger->log("approved status: " . $status . " not found in: " . var_export($allowedStatuses, 1));
        }

        return $status;
    }

    /**
     * @return mixed|string
     */
    public function getSelectedDeclinedState()
    {
        $state = $this->apiConfig->getDeclinedState();
        if (!in_array($state, [\Magento\Sales\Model\Order::STATE_CANCELED, \Magento\Sales\Model\Order::STATE_HOLDED])) {
            $state = \Magento\Sales\Model\Order::STATE_CANCELED;
        }

        return $state;
    }

    /**
     * @return mixed|string
     */
    public function getSelectedDeclinedStatus()
    {
        $status = $this->apiConfig->getDeclinedStatus();

        $orderConfig = $this->configFactory->create();
        $allowedStatuses = $orderConfig->getStateStatuses($this->getSelectedDeclinedState());
        if (!in_array($status, array_keys($allowedStatuses))) {
            $status = $this->getRiskifiedDeclinedStatusCode();
            $this->apiLogger->log("declined status: " . $status . " not found in: " . var_export($allowedStatuses, 1));
        }

        return $status;
    }
}
