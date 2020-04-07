<?php

namespace Riskified\Decider\Model\Config\Source;

use Magento\Sales\Model\Order;

class DeclinedState implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Order::STATE_CANCELED,
                'label' => __(Order::STATE_CANCELED)
            ],
            [
                'value' => Order::STATE_HOLDED,
                'label' => __(Order::STATE_HOLDED)]
        ];
    }
}
