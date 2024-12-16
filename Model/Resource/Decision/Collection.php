<?php

namespace Riskified\Decider\Model\Resource\Decision;

use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Riskified\Decider\Model\Api\Decision', 'Riskified\Decider\Model\Resource\Decision');
    }
}
