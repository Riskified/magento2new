<?php

namespace Riskified\Decider\Controller\Response;

class Bin extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * Bin constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $card_no = $this->getRequest()->getParam('card', null);
        $this->checkoutSession->setRiskifiedBin($card_no);
    }
}
