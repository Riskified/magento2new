<?php
namespace Riskified\Decider\Api\Order;
use Riskified\OrderWebhook\Model;

class Config
{
    private $apiConfig;
    private $apiLogger;
    private $configFactory;

    public function __construct(
        \Riskified\Decider\Api\Config $apiConfig,
        \Riskified\Decider\Api\Log $apiLogger,
        \Magento\Sales\Model\Order\ConfigFactory $configFactory
    )
    {
        $this->apiConfig        = $apiConfig;
        $this->apiLogger        = $apiLogger;
        $this->configFactory    = $configFactory;
    }

    public function getOnHoldStatusCode()
    {
        return 'riskified_holded';
    }
    public function getOnHoldStatusLabel()
    {
        return 'Under Review (Riskified)';
    }
    public function getTransportErrorStatusCode()
    {
        return 'riskified_trans_error';
    }
    public function getTransportErrorStatusLabel()
    {
        return 'Transport Error (Riskified)';
    }
    public function getRiskifiedDeclinedStatusCode()
    {
        return 'riskified_declined';
    }
    public function getRiskifiedDeclinedStatusLabel()
    {
        return 'Declined (Riskified)';
    }
    public function getRiskifiedApprovedStatusCode()
    {
        return 'riskified_approved';
    }
    public function getRiskifiedApprovedStatusLabel()
    {
        return 'Approved (Riskified)';
    }
    public function getSelectedApprovedState()
    {
        $state = $this->apiConfig->getApprovedState();
        if (!in_array($state, array(\Magento\Sales\Model\Order::STATE_PROCESSING,\Magento\Sales\Model\Order::STATE_HOLDED))) {
            $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
        }
        return $state;
    }
    public function getSelectedApprovedStatus()
    {
        $status = $this->apiConfig->getApprovedStatus();
        $orderConfig = $this->configFactory->create();

        $allowedStatuses = $orderConfig->getStateStatuses($this->getSelectedApprovedState());
        if (!in_array($status, array_keys($allowedStatuses))) {
            $status = $this->getRiskifiedApprovedStatusCode();
            $this->apiLogger->log("approved status: ".$status." not found in: ".var_export($allowedStatuses,1));
        }
        return $status;
    }
    public function getSelectedDeclinedState()
    {
        $state = $this->apiConfig->getDeclinedState();
        if (!in_array($state, array(\Magento\Sales\Model\Order::STATE_CANCELED,\Magento\Sales\Model\Order::STATE_HOLDED))) {
            $state = \Magento\Sales\Model\Order::STATE_CANCELED;
        }
        return $state;
    }
    public function getSelectedDeclinedStatus()
    {
        $status = $this->apiConfig->getDeclinedStatus();

        $orderConfig = $this->configFactory->create();
        $allowedStatuses = $orderConfig->getStateStatuses($this->getSelectedDeclinedState());
        if (!in_array($status, array_keys($allowedStatuses))) {
            $status = $this->getRiskifiedDeclinedStatusCode();
            $this->apiLogger->log("declined status: ".$status." not found in: ".var_export($allowedStatuses,1));
        }
        return $status;
    }
}