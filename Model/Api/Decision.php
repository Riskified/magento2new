<?php

namespace Riskified\Decider\Model\Api;

use Magento\Framework\Model\AbstractExtensibleModel;
use Riskified\Decider\Api\Data\DecisionInterface;

class Decision extends AbstractExtensibleModel implements DecisionInterface
{
    protected function _construct()
    {
        $this->_init(\Riskified\Decider\Model\Resource\Decision::class);
    }
    /**
     * @return int
     */
    public function getOrderId() : int
    {
        return (int) $this->getData('order_id');
    }

    /**
     * @return string
     */
    public function getDecision() : string
    {
        return (string) $this->getData('decision');
    }

    /**
     * @param int $orderId
     * @return mixed|Decision
     */
    public function setOrderId(int $orderId)
    {
        return $this->setData('order_id', $orderId);
    }

    /**
     * @param string $decision
     * @return mixed|Decision
     */
    public function setDecision(string $decision)
    {
        return $this->setData('decision', $decision);
    }

    /**
     * @param string $description
     * @return mixed|Decision
     */
    public function setDescription(string $description)
    {
        return $this->setData('description', $description);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return (string) $this->getData('description');
    }

    /**
     * @param string $datetime
     * @return mixed|Decision
     */
    public function setCreatedAt(string $datetime)
    {
        return $this->setData('created_at', $datetime);
    }

    /**
     * @param int $attemptsCount
     * @return mixed
     */
    public function setAttemptsCount(int $attemptsCount)
    {
        return $this->setData('attempts_count', $attemptsCount);
    }

    /**
     * @return int
     */
    public function getAttemptsCount(): int
    {
        return (int) $this->getData('attemps_count');
    }
}
