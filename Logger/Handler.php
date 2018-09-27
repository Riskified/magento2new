<?php

namespace Riskified\Decider\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;
    /**
     * @var string
     */
    protected $fileName = '/var/log/riskified_decider.log';
}
