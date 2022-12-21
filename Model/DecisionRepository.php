<?php

namespace Riskified\Decider\Model;

use Riskified\Decider\Api\Data\DecisionInterface as Decision;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Resource\Decision as DecisionResourceModel;

class DecisionRepository implements DecisionRepositoryInterface
{
    private DecisionResourceModel $resourceModel;

    public function __construct(DecisionResourceModel $resourceModel)
    {
        $this->resourceModel = $resourceModel;
    }

    public function save(Decision $decision)
    {
        $this->resourceModel->save($decision);
    }

    public function getById(int $decisionId) : Decision
    {
        return $this->resourceModel->load(Decision::class, $decisionId);
    }

    public function getByOrderId(int $orderId) : Decision
    {
        return $this->resourceModel->load(Decision::class, $orderId, 'order_id');
    }

    public function deleteById(int $decisionId) : void
    {
        $decision = $this->getById($decisionId);
        $decision->delete();
    }

    public function deleteByOrderId(int $orderId) : void
    {
        $decision = $this->getByOrderId($orderId);
        $decision->delete();
    }
}
