<?php
namespace Riskified\Full\Config\Source;

class HoldedStateStatuses implements \Magento\Framework\Option\ArrayInterface
{
    private $_configFactory;

    public function __construct(
        \Magento\Sales\Model\Order\ConfigFactory $configFactory
    ) {
        $this->_configFactory = $configFactory;
    }
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $orderConfig = $this->_configFactory->create();
        $arr = $orderConfig->getStateStatuses(\Magento\Sales\Model\Order::STATE_HOLDED);
        return array_map(function($status_code,$status_label) {
            return array('value' => $status_code, 'label' => __($status_label));
        }, array_keys($arr),$arr);
    }
}