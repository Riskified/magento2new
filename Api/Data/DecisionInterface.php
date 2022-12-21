<?php
namespace Riskified\Decider\Api\Data;

interface DecisionInterface
{
    public function getOrderId() : int;
    public function getDecision() : string;
}
