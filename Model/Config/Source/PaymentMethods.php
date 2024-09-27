<?php

namespace Riskified\Decider\Model\Config\Source;

use \Magento\Payment\Helper\Data as PaymentData;

class PaymentMethods implements \Magento\Framework\Data\OptionSourceInterface
{
    private PaymentData $paymentHelper;
    public function __construct(
        PaymentData $paymentHelper,
    ) {
        $this->paymentHelper = $paymentHelper;
    }
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $list = $this->paymentHelper->getPaymentMethodList();

        $data = [];

        foreach ($list as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => __($value)
            ];
        }

        return $data;
    }
}
