<?php

namespace Riskified\Decider\Controller\Adminhtml\Riskified;

use Riskified\Decider\Model\Api\Order as OrderApi;

class Send extends \Magento\Backend\App\Action
{
    public const ADMIN_RESOURCE = 'Magento_Sales::sales_order';

    /**
     * @var OrderApi
     */
    protected $apiOrderLayer;

    /**
     * Send constructor.
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
        $id = $this->getRequest()->getParam('order_id');
        $this->apiOrderLayer->sendOrders([$id]);
        $this->_redirect("sales/order/view", ['order_id' => $id]);
    }
}
