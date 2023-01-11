<?php

namespace Riskified\Decider\Model;

use Riskified\Decider\Api\Data\DecisionInterface as Decision;
use Riskified\Decider\Api\DecisionRepositoryInterface;
use Riskified\Decider\Model\Api\DecisionFactory;
use Riskified\Decider\Model\Resource\Decision as DecisionResourceModel;
use Riskified\Decider\Model\Resource\Decision\Collection as DecisionCollectionResourceModel;

class DecisionRepository implements DecisionRepositoryInterface
{
    private DecisionResourceModel $resourceModel;
    private DecisionFactory $decisionFactory;
    private DecisionCollectionResourceModel $decisionCollection;

    public function __construct(
        DecisionResourceModel $resourceModel,
        DecisionFactory $decisionFactory,
        DecisionCollectionResourceModel $decisionCollection
    ) {
        $this->resourceModel = $resourceModel;
        $this->decisionFactory = $decisionFactory;
        $this->decisionCollection = $decisionCollection;
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

    public function getOldDecisionEntries() : array
    {
        $currentDateTime = new \DateTime();
        $currentDateTime->modify("-90 days");

        $collection = $this->decisionCollection
            ->addFieldToFilter(
                'created_at',
                ['gteq' => $currentDateTime->format("Y-m-d H:i:s")]
            )
            ->getItems();

        return $collection;
    }
}
