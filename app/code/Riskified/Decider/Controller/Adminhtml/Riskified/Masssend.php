<?php
namespace Riskified\Decider\Controller\Adminhtml\Riskified;

class Masssend extends \Magento\Backend\App\Action
{

    protected $apiOrderLayer;

    public function __construct(\Magento\Backend\App\Action\Context $context,
                                \Riskified\Decider\Api\Order $apiOrderLayer
    )
    {
        parent::__construct($context);
        $this->apiOrderLayer = $apiOrderLayer;
    }

    public function execute()
    {
        $ids = $this->getRequest()->getParam('selected');
        $sendCount = $this->apiOrderLayer->sendOrders($ids);
        $this->messageManager->addSuccess(__('%1 order(s) was sent to Riskified', $sendCount));
        $this->_redirect("sales/order");
    }
}