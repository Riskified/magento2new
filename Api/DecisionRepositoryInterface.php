<?php
namespace Riskified\Decider\Api;

use Riskified\Decider\Api\Data\DecisionInterface as Decision;

interface DecisionRepositoryInterface
{
    public function save(Decision $decision);
    public function getOldDecisionEntries() : array;
    public function getById(int $decisionId) : Decision;
    public function getByOrderId(int $orderId) : Decision;
    public function deleteById(int $decisionId) : void;
    public function deleteByOrderId(int $orderId) : void;
}
