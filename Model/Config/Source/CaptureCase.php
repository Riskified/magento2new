<?php

namespace Riskified\Decider\Model\Config\Source;

use Magento\Sales\Model\Order\Invoice;

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
            ['value' => Invoice::CAPTURE_ONLINE, 'label' => __('Capture Online')],
            ['value' => Invoice::CAPTURE_OFFLINE, 'label' => __('Capture Offline')]
        ];
    }
}
