<?php

namespace Riskified\Decider\Api\Order\PaymentProcessor;

abstract class AbstractPayment
{
    protected $payment;
    protected $order;

    /**
     * @param $payment
     *
     * @return $this
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;

        return $this;
    }

    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return array
     */
    public function getDetails()
    {
        return [];
    }
}
