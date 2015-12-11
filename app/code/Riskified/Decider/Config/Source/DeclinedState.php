<?php
namespace Riskified\Decider\Config\Source;

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
            ['value' => \Magento\Sales\Model\Order::STATE_CANCELED, 'label' => __(\Magento\Sales\Model\Order::STATE_CANCELED)],
            ['value' => \Magento\Sales\Model\Order::STATE_HOLDED, 'label' => __(\Magento\Sales\Model\Order::STATE_HOLDED)]];
    }
}