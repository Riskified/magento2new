<?php
namespace Riskified\Decider\Config\Source;

class ProcessingStateStatuses implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Magento\Sales\Model\Order\ConfigFactory $configFactory
    )
    {
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
        $arr = $orderConfig->getStateStatuses(\Magento\Sales\Model\Order::STATE_PROCESSING);
        return array_map(function ($status_code, $status_label) {
            return array('value' => $status_code, 'label' => __($status_label));
        }, array_keys($arr), $arr);
    }
}