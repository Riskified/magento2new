<?php

namespace Riskified\Decider\Model\Api\Order;

use Riskified\Decider\Model\Api\Config as ApiConfig;
use Riskified\Decider\Model\Api\Log as ApiLog;
use Magento\Sales\Model\Order\ConfigFactory;
use Magento\Sales\Model\Order;

class Config
{
    /**
     * @var ApiConfig
     */
    private $apiConfig;

    /**
     * @var ApiLog
     */
    private $apiLogger;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * Config constructor.
     *
     * @param ApiConfig $apiConfig
     * @param ApiLog $apiLogger
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        ApiConfig $apiConfig,
        ApiLog $apiLogger,
        ConfigFactory $configFactory
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
        if (!in_array($state, array(Order::STATE_PROCESSING, Order::STATE_HOLDED))) {
            $state = Order::STATE_PROCESSING;
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
        if (!in_array($state, [Order::STATE_CANCELED, Order::STATE_HOLDED])) {
            $state = Order::STATE_CANCELED;
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
