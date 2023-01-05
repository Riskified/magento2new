<?php

namespace Riskified\Decider\Controller\Adminhtml\Riskified;

use Riskified\Decider\Model\Api\Order as OrderApi;

class Masssend extends \Magento\Backend\App\Action
{
    public const ADMIN_RESOURCE = 'Magento_Sales::sales_order';

    /**
     * @var OrderApi
     */
    protected $apiOrderLayer;

    /**
     * Masssend constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param OrderApi $apiOrderLayer
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        OrderApi $apiOrderLayer
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
        $this->messageManager->addSuccess(
            __('%1 order(s) was sent to Riskified', $sendCount)
        );
        $this->_redirect("sales/order");
    }
}
