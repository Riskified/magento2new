<?php

namespace Riskified\Decider\Controller\Response;

class Bin extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * Bin constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $card_no = $this->getRequest()->getParam('card', null);
        $this->customerSession->setRiskifiedBin($card_no);
    }
}
