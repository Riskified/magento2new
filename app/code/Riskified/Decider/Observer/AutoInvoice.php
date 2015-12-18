<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Service\InvoiceService;

class AutoInvoice implements ObserverInterface
{
    protected $logger;
    protected $apiOrder;
    protected $apiConfig;
    protected $invoiceService;
    protected $context;
    protected $objectManager;

    public function __construct(
        \Riskified\Decider\Api\Order\Log $logger,
        \Riskified\Decider\Api\Order $logger,
        \Riskified\Decider\Api\Config $apiConfig,
        \Riskified\Decider\Api\Order $orderApi,
        InvoiceService $invoiceService,
        \Magento\Framework\Model\Context $context
    ){
        $this->logger = $logger;
        $this->apiOrder = $orderApi;
        $this->apiConfig = $apiConfig;
        $this->invoiceService = $invoiceService;
        $this->context = $context;
        $this->objectManager = $context->getObjectManager();
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->apiConfig->isAutoInvoiceEnabled()) {
            return;
        }
        $order = $observer->getOrder();
        if (!$order || !$order->getId()) {
            return;
        }
        $this->logger->log("Auto-invoicing  order " . $order->getId());

        if (!$order->canInvoice() || $order->getState() != \Magento\Sales\Model\Order::STATE_PROCESSING) {
            $this->logger->log("Order cannot be invoiced");
            if($this->apiConfig->isDebugLogsEnabled()) {
                $this->logger->logInvoice($order);
            }
            return;
        }
        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$invoice->getTotalQty()) {
            $this->logger->log("Cannot create an invoice without products");
            return;
        }
        try {
            $invoice
                ->setRequestedCaptureCase($this->apiConfig->getCaptureCase())
                ->addComment(
                    'Invoice automatically created by Riskified when order was approved',
                    false,
                    false
                )
                ->register();
        } catch (\Exception $e) {
            $this->logger->log("Error creating invoice: " . $e->getMessage());
            return;
        }
        try {
            $this->objectManager->create(
                'Magento\Framework\DB\Transaction'
            )->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            )->save();
        } catch (\Exception $e) {
            $this->logger->log("Error creating transaction: " . $e->getMessage());
            return;
        }
        $this->logger->log("Transaction saved");
    }
}
