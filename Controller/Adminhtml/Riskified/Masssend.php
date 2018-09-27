<?php

namespace Riskified\Decider\Controller\Adminhtml\Riskified;

class Masssend extends \Magento\Backend\App\Action
{
    /**
     * @var \Riskified\Decider\Api\Order
     */
    protected $apiOrderLayer;

    /**
     * Masssend constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Riskified\Decider\Api\Order $apiOrderLayer
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Riskified\Decider\Api\Order $apiOrderLayer
    ) {
        parent::__construct($context);
        $this->apiOrderLayer = $apiOrderLayer;
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $ids = $this->getRequest()->getParam('selected');
        $sendCount = $this->apiOrderLayer->sendOrders($ids);
        $this->messageManager->addSuccess(__('%1 order(s) was sent to Riskified', $sendCount));
        $this->_redirect("sales/order");
    }
}
