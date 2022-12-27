<?php

namespace Riskified\Decider\Model;

use Riskified\Decider\Api\Data\DecisionInterface as Decision;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\DecisionFactory;
use Riskified\Decider\Model\Resource\Decision as DecisionResourceModel;

class DecisionRepository implements DecisionRepositoryInterface
{
    private DecisionResourceModel $resourceModel;
    private DecisionFactory $decisionFactory;

    public function __construct(DecisionResourceModel $resourceModel, DecisionFactory $decisionFactory)
    {
        $this->resourceModel = $resourceModel;
        $this->decisionFactory = $decisionFactory;
    }

    public function save(Decision $decision)
    {
        $this->resourceModel->save($decision);
    }

    public function getById(int $decisionId) : Decision
    {
        $decision = $this->decisionFactory->create();

        return $decision->load($decisionId);
    }

    public function getByOrderId(int $orderId) : Decision
    {
        $decision = $this->decisionFactory->create();

        return $decision->load($orderId, 'order_id');
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
