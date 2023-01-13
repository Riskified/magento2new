<?php
namespace Riskified\Decider\Api\Data;

interface DecisionInterface
{
    /**
     * @param int $orderId
     * @return mixed
     */
    public function setOrderId(int $orderId);

    /**
     * @return int
     */
    public function getOrderId() : int;

    /**
     * @param string $decision
     * @return mixed
     */
    public function setDecision(string $decision);

    /**
     * @return string
     */
    public function getDecision() : string;

    /**
     * @param string $description
     * @return mixed
     */
    public function setDescription(string $description);

    /**
     * @return string
     */
    public function getDescription() : string;

    /**
     * @param string $datetime
     * @return mixed
     */
    public function setCreatedAt(string $datetime);

    /**
     * @param int $attemptsCount
     * @return mixed
     */
    public function setAttemptsCount(int $attemptsCount);

    /**
     * @return int
     */
    public function getAttemptsCount() : int;
}
