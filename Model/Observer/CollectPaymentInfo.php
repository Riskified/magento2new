<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Locale\Resolver;

class CollectPaymentInfo implements ObserverInterface
{
    /**
     * CollectPaymentInfo constructor.
     */
    public function __construct(
        private Resolver $localeResolver,
        private Header $httpHeader,
        private State $state
    ) {}

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $payment = $observer->getQuote()->getPayment();
        $order = $observer->getOrder();

        if ($this->state->getAreaCode() != Area::AREA_ADMINHTML) {
            if (!$order->getAcceptLanguage()) {
                $order->setAcceptLanguage($this->localeResolver->getLocale());
            }

            if (!$order->getUserAgent()) {
                $order->setUserAgent($this->httpHeader->getHttpUserAgent());
            }
        }

        if (empty($payment->getCcNumber())) {
            return;
        }

        $cc_bin = substr($payment->getCcNumber(), 0, 6);
        if ($cc_bin) {
            $payment->setAdditionalInformation('riskified_cc_bin', $cc_bin);
        }
    }
}
