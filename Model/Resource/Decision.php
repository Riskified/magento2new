<?php

namespace Riskified\Decider\Model\Resource;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Decision extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('riskified_decision_queue', 'id');
    }
}
