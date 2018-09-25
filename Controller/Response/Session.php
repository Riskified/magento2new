<?php
namespace Riskified\Decider\Controller\Response;

class Session extends \Magento\Framework\App\Action\Action
{
    private $customerSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->resultFactory = $context->getResultFactory();
    }

    public function execute()
    {
        $result = $this->resultFactory->create("json");
        $payload = ['session_id' => $this->customerSession->getSessionId()];
        $result = $result->setData($payload);

        return $result;
    }
}
