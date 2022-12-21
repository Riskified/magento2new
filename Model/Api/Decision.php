<?php

namespace Riskified\Decider\Model\Api;

use Magento\Framework\Api\AbstractSimpleObject;
use Riskified\Decider\Api\Data\DecisionInterface;

class Decision extends AbstractSimpleObject implements DecisionInterface
{
    public function getOrderId() : int
    {
        return (int) $this->_get('order_id');
    }

    public function getDecision() : string
    {
        return (string) $this->_get('decision');
    }
}
