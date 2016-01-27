<?php
namespace Riskified\Decider\Controller\Response;

use \Riskified\DecisionNotification;

class Bin extends \Magento\Framework\App\Action\Action
{
    private $customerSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession
    )
    {
        parent::__construct($context);
        $this->customerSession = $customerSession;
    }


    public function execute()
    {
        $card_no = $this->getRequest()->getParam('card', null);
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $session = $om->get('Magento\Customer\Model\Session');
        $session->setRiskifiedBin($card_no);
    }
}
