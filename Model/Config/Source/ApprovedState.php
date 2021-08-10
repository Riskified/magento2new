<?php

namespace Riskified\Decider\Model\Config\Source;

use Magento\Sales\Model\Order;

class ApprovedState implements \Magento\Framework\Option\ArrayInterface
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
                'value' => Order::STATE_PROCESSING,
                'label' => __(Order::STATE_PROCESSING)
            ],
            [
                'value' => Order::STATE_HOLDED,
                'label' => __(Order::STATE_HOLDED)
            ]
        ];
    }
}
