<?php

namespace Riskified\Decider\Model\Api\Order;

use Magento\Framework\ObjectManagerInterface;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\AbstractPayment;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Adyen;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Authcim;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Braintree;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Cryozonic;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\DefaultPaymentProcessor;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\DirectPost;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\OptimalHosted;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Payflowpro;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Paypal;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\PaypalDirect;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\SagePay;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Transarmor;
use Riskified\Decider\Model\Api\Order\PaymentProcessor\Vantiv;

class PaymentProcessorFactory
{
    const GATEWAY_INSTANCE = [
        'default' => DefaultPaymentProcessor::class,
        'authorizenet_directpost' => DirectPost::class,
        'authnetcim' => Authcim::class,
        'optimal_hosted' => OptimalHosted::class,
        'paypal_express' => Paypal::class,
        'paypaluk_express' => Paypal::class,
        'paypal_standard' => Paypal::class,
        'payflow_express' => Paypal::class,
        'paypal_direct' => PaypalDirect::class,
        'paypaluk_direct' => PaypalDirect::class,
        'sagepaydirectpro' => SagePay::class,
        'sage_pay_form' => SagePay::class,
        'sagepayserver' => SagePay::class,
        'transarmor' => Transarmor::class,
        'braintree' => Braintree::class,
        'payflowpro' => Payflowpro::class,
        'adyen_oneclick' => Adyen::class,
        'adyen_cc' => Adyen::class,
        'adyen_hpp' => Paypal::class,
        'cryozonic_stripe' => Cryozonic::class,
        'vantiv_cc' => Vantiv::class
    ];

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * PaymentProcessor constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param $order
     *
     * @return AbstractPayment
     */
    public function create($order)
    {
        if (isset(self::GATEWAY_INSTANCE[$order->getPayment()->getMethod()])) {
            $model = $this->objectManager->create(self::GATEWAY_INSTANCE[$order->getPayment()->getMethod()]);
        } else {
            $model = $this->objectManager->create(self::GATEWAY_INSTANCE['default']);
        }

        return $model->setOrder($order)->setPayment($order->getPayment());
    }
}
