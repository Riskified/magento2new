<?php

namespace Riskified\Decider\Api\Order;

use Magento\Framework\ObjectManagerInterface;

class PaymentProcessorFactory
{
    const GATEWAY_INSTANCE = [
        'default' => \Riskified\Decider\Api\Order\PaymentProcessor\AbstractPayment::class,
        'authorizenet_directpost' => \Riskified\Decider\Api\Order\PaymentProcessor\DirectPost::class,
        'authnetcim' => \Riskified\Decider\Api\Order\PaymentProcessor\Authcim::class,
        'optimal_hosted' => \Riskified\Decider\Api\Order\PaymentProcessor\OptimalHosted::class,
        'paypal_express' => \Riskified\Decider\Api\Order\PaymentProcessor\Paypal::class,
        'paypaluk_express' => \Riskified\Decider\Api\Order\PaymentProcessor\Paypal::class,
        'paypal_standard' => \Riskified\Decider\Api\Order\PaymentProcessor\Paypal::class,
        'payflow_express' => \Riskified\Decider\Api\Order\PaymentProcessor\Paypal::class,
        'paypal_direct' => \Riskified\Decider\Api\Order\PaymentProcessor\PaypalDirect::class,
        'paypaluk_direct' => \Riskified\Decider\Api\Order\PaymentProcessor\PaypalDirect::class,
        'sagepaydirectpro' => \Riskified\Decider\Api\Order\PaymentProcessor\SagePay::class,
        'sage_pay_form' => \Riskified\Decider\Api\Order\PaymentProcessor\SagePay::class,
        'sagepayserver' => \Riskified\Decider\Api\Order\PaymentProcessor\SagePay::class,
        'transarmor' => \Riskified\Decider\Api\Order\PaymentProcessor\Transarmor::class,
        'braintree' => \Riskified\Decider\Api\Order\PaymentProcessor\Braintree::class,
        'payflowpro' => \Riskified\Decider\Api\Order\PaymentProcessor\Payflowpro::class,
        'adyen_oneclick' => \Riskified\Decider\Api\Order\PaymentProcessor\Adyen::class,
        'adyen_cc' => \Riskified\Decider\Api\Order\PaymentProcessor\Adyen::class,
        'cryozonic_stripe' => \Riskified\Decider\Api\Order\PaymentProcessor\Cryozonic::class,
        'vantiv_cc' => \Riskified\Decider\Api\Order\PaymentProcessor\Vantiv::class
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
     * @return \Riskified\Decider\Api\Order\PaymentProcessor\AbstractPayment
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
