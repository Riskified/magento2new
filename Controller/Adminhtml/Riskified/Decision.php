<?php

namespace Riskified\Decider\Controller\Adminhtml\Riskified;

use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Riskified\Decider\Model\Api\Api;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Observer\UpdateOrderState;

class Decision extends \Magento\Backend\App\Action
{
    /**
     * @var OrderApi
     */
    protected OrderApi $api;
    protected OrderRepositoryInterface $orderRepository;
    protected UpdateOrderState $updateOrderStateObserver;

    /**
     * Send constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderApi $api
     * @param UpdateOrderState $updateOrderStateObserver
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderApi $api,
        UpdateOrderState $updateOrderStateObserver
    ) {
        $this->orderRepository = $orderRepository;
        $this->updateOrderStateObserver = $updateOrderStateObserver;
        $this->api = $api;

        parent::__construct($context);
    }

    /**
     * Execute.
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('order_id');
        try {
            $order = $this->orderRepository->get($id);

            $response = $this->api->post($order, Api::ACTION_UPDATE);

            $approvedStatuses = ['approved', 'declined'];

            if (in_array($response->order->status, $approvedStatuses)) {
                $observer = new Observer();
                $observer->setOrder($order);
                $observer->setStatus($response->order->status);
                $observer->setStatusOld($response->order->old_status);
                $observer->setDescription($response->order->description);

                $this->updateOrderStateObserver->execute($observer);

                $this->messageManager->addSuccessMessage(
                    __('Updated order status base on Riskified decision.')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Decision was not found or not passing validation')
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Internal Error')
            );
        }

        $this->_redirect("sales/order/view", ['order_id' => $id]);
    }
}
