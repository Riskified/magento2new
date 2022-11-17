<?php
namespace Riskified\Decider\Model\Api\Request;

use Riskified\Common\Signature\HttpDataSignature;
use Riskified\OrderWebhook\Transport\CurlTransport;

/**
 * Class Advice
 * @package Riskified\Decider\Api\Request
 */
class Advice extends CurlTransport
{
    public function __construct()
    {
        parent::__construct(new HttpDataSignature(), null);
    }

    /**
     * @param $json
     * @return mixed
     * @throws \Riskified\OrderWebhook\Exception\CurlException
     * @throws \Riskified\OrderWebhook\Exception\UnsuccessfulActionException
     */
    public function call($json)
    {
        return $this->send_json_request($json, 'advise');
    }
}
