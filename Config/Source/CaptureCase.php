<?php

namespace Riskified\Decider\Config\Source;

class CaptureCase implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE, 'label' => __('Capture Online')],
            ['value' => \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE, 'label' => __('Capture Offline')]
        ];
    }
}