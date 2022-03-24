<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\Event\ObserverInterface;

class ImportDataBefore implements ObserverInterface
{
    private $registry;

    /**
     * ImportDataBefore constructor.
     */
    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $additionalData = $observer->getInput()->getAdditionalData();

        if ($additionalData && is_array($additionalData)) {
            if (isset($additionalData['cc_bin']) && $additionalData['cc_bin']) {
                $this->registry->unregister('riskified_cc_bin');
                $this->registry->register('riskified_cc_bin', $additionalData['cc_bin']);

                $observer->getPayment()->setAdditionalInformation('riskified_cc_bin', $additionalData['cc_bin']);

                return $this;
            }
        }
    }
}
