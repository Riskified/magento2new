<?php
namespace Riskified\Decider\Model\Gateway\Adyen\Response;

use Magento\Framework\Registry;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Logger\Order as OrderLogger;

class PaymentAuthorisationDetailsHandler
{
    /**
     * @var OrderLogger
     */
    private $log;

    /**
     * @var OrderApi
     */
    private $api;

    /**
     * @var Registry
     */
    private $registry;

    public function __construct(
        OrderLogger $logger,
        OrderApi $orderApi,
        Registry $registry
    ) {
        $this->log = $logger;
        $this->api = $orderApi;
        $this->registry = $registry;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function afterHandle(array $handlingSubject, array $response)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);
        $payment = $payment->getPayment();
        $order = $payment->getOrder();

        try {
            $this->registry->register("riskified-order", $order);
            $this->registry->register("riskified-place-order-after", true, true);

            $this->api->post($order, Api::ACTION_UPDATE);
        } catch (\Exception $e) {
            $this->log->critical($e);
        }
    }
}
