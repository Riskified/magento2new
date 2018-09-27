<?php

namespace Riskified\Decider\Config\Source;

class Env implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'PROD', 'label' => __('Production')],
            ['value' => 'SANDBOX', 'label' => __('Sandbox')],
            ['value' => 'DEV', 'label' => __('Dev')]
        ];
    }
}
