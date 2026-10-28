<?php

namespace Riskified\Decider\Model\Api\Order;

use Exception;

class StripeHelper
{
    const METHOD_CODE = 'stripe_payments';

    /**
     * @var PaymentProcessorFactory
     */
    private $paymentProcessorFactory;

    public function __construct(PaymentProcessorFactory $paymentProcessorFactory)
    {
        $this->paymentProcessorFactory = $paymentProcessorFactory;
    }

    /**
     * Stripe payment method type (card, paypal, klarna, ...) or null for non-Stripe orders.
     */
    public function getPaymentMethodType($order): ?string
    {
        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() !== self::METHOD_CODE) {
            return null;
        }
        try {
            return $this->paymentProcessorFactory->create($order)->getPaymentMethodType();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Gateway split per Stripe payment method type (stripe_card, stripe_paypal, stripe_klarna).
     */
    public function getGateway($order): ?string
    {
        $type = $this->getPaymentMethodType($order);

        return $type ? 'stripe_' . $type : null;
    }
}
