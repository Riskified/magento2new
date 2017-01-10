<?php
namespace Riskified\Decider\Logger;

class Order extends \Monolog\Logger
{
    /**
     * @inheritdoc
     */
    public function __construct($name, array $handlers = array(), array $processors = array())
    {
        parent::__construct($name, $handlers, $processors);
    }
}