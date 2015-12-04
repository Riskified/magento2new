<?php
namespace Riskified\Full\Config\Source;

class ProcessingStateStatuses implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Magento\Sales\Model\Order::STATE_PROCESSING, 'label' => __(\Magento\Sales\Model\Order::STATE_PROCESSING)],
            ['value' => \Magento\Sales\Model\Order::STATE_HOLDED, 'label' => __(\Magento\Sales\Model\Order::STATE_HOLDED)]];
    }
}