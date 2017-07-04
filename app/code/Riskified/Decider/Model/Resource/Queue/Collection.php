<?php

namespace Riskified\Decider\Model\Resource\Queue;

use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Riskified\Decider\Model\Queue', 'Riskified\Decider\Model\Resource\Queue');
    }
}