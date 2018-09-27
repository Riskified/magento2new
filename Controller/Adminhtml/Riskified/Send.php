<?php

namespace Riskified\Decider\Controller\Adminhtml\Riskified;

class Send extends \Magento\Backend\App\Action
{
    /**
     * @var \Riskified\Decider\Api\Order
     */
    protected $apiOrderLayer;

    /**
     * Send constructor.
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
        $id = $this->getRequest()->getParam('order_id');
        $this->apiOrderLayer->sendOrders([$id]);
        $this->_redirect("sales/order/view", ['order_id' => $id]);
    }
}