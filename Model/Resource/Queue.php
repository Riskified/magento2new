<?php

namespace Riskified\Decider\Model\Resource;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Queue extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('riskified_queue', 'retry_id');
    }
}
