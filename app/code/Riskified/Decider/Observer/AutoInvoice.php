<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\App\ObjectManagerFactory;

class AutoInvoice implements ObserverInterface
{
    protected $logger;
    protected $apiOrder;
    protected $apiOrderLogger;
    protected $apiConfig;
    protected $invoiceService;
    protected $context;
    protected $objectManager;

    public function __construct(
        \Riskified\Decider\Api\Order\Log $apiOrderLogger,
        \Riskified\Decider\Logger\Order $logger,
        \Riskified\Decider\Api\Config $apiConfig,
        \Riskified\Decider\Api\Order $orderApi,
        InvoiceService $invoiceService,
        \Magento\Framework\Model\Context $context,
        ObjectManagerFactory $objectManagerFactory
    ){
        $this->logger           = $logger;
        $this->context          = $context;
        $this->apiOrder         = $orderApi;
        $this->apiConfig        = $apiConfig;
        $this->apiOrderLogger   = $apiOrderLogger;
        $this->invoiceService   = $invoiceService;
        $this->objectManager    = $objectManagerFactory;
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
        $this->logger->addInfo("Auto-invoicing  order " . $order->getId());

        if (!$order->canInvoice() || $order->getState() != \Magento\Sales\Model\Order::STATE_PROCESSING) {
            $this->logger->addInfo("Order cannot be invoiced");
            if($this->apiConfig->isLoggingEnabled()) {
                $this->apiOrderLogger->logInvoice($order);
            }
            return;
        }
        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$invoice->getTotalQty()) {
            $this->logger->addInfo("Cannot create an invoice without products");
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
            $this->logger->addInfo("Error creating invoice: " . $e->getMessage());
            return;
        }
        try {
            $invoice->save();
            $invoice->getOrder()->save();
        } catch (\Exception $e) {
            $this->logger->addCritical("Error creating transaction: " . $e->getMessage());
            return;
        }
        $this->logger->addInfo("Transaction saved");
    }
}
