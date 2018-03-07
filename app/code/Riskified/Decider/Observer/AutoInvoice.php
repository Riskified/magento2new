<?php

namespace Riskified\Decider\Observer;

use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Order as OrderEntity;
use Magento\Sales\Model\Service\InvoiceService;
use Riskified\Decider\Api\Config;
use Riskified\Decider\Api\Order as OrderApi;
use Riskified\Decider\Api\Order\Log;
use Riskified\Decider\Logger\Order;

/**
 * Observer Auto Invoice Class.
 * Creates invoice when order was approved in Riskified.
 *
 * @category Riskified
 * @package  Riskified_Decider
 */
class AutoInvoice implements ObserverInterface
{
    /**
     * Module main logger class.
     *
     * @var Order
     */
    private $logger;

    /**
     * Module api class.
     *
     * @var OrderApi
     */
    private $apiOrder;

    /**
     * Api logger.
     *
     * @var Log
     */
    private $apiOrderLogger;

    /**
     * Module config.
     *
     * @var Config
     */
    private $apiConfig;

    /**
     * Magento's invoice service.
     *
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * Context class.
     *
     * @var Context
     */
    private $context;

    /**
     * Object Manager class.
     *
     * @var ObjectManagerFactory
     */
    private $objectManager;

    /**
     * State class used to emulate admin scope during invoice creation.
     *
     * @var State
     */
    private $state;

    /**
     * AutoInvoice constructor.
     *
     * @param Log                  $apiOrderLogger
     * @param Order                $logger
     * @param Config               $apiConfig
     * @param OrderApi             $orderApi
     * @param InvoiceService       $invoiceService
     * @param Context              $context
     * @param ObjectManagerFactory $objectManagerFactory
     */
    public function __construct(
        Log $apiOrderLogger,
        Order $logger,
        Config $apiConfig,
        OrderApi $orderApi,
        InvoiceService $invoiceService,
        Context $context,
        ObjectManagerFactory $objectManagerFactory
    ) {
        $this->logger = $logger;
        $this->context = $context;
        $this->apiOrder = $orderApi;
        $this->apiConfig = $apiConfig;
        $this->apiOrderLogger = $apiOrderLogger;
        $this->invoiceService = $invoiceService;
        $this->objectManager = $objectManagerFactory;
        $this->state = $context->getAppState();
    }

    /**
     * Main method ran during event raise.
     *
     * @param Observer $observer
     *
     * @return bool
     */
    public function execute(Observer $observer)
    {
        if (!$this->canRun()) {
            return false;
        }

        $order = $observer->getOrder();

        if (!$order || !$order->getId()) {
            return false;
        }
        $this->logger->addInfo('Auto-invoicing  order ' . $order->getId());

        if (!$order->canInvoice()
            || $order->getState() != OrderEntity::STATE_PROCESSING
        ) {
            $this->logger->addInfo('Order cannot be invoiced');
            if ($this->apiConfig->isLoggingEnabled()) {
                $this->apiOrderLogger->logInvoice($order);
            }

            return false;
        }

        $invoice = $this->state->emulateAreaCode(
            'adminhtml',
            [$this->invoiceService, 'prepareInvoice'],
            [$order]
        );

        if (!$invoice->getTotalQty()) {
            $this->logger->addInfo('Cannot create an invoice without products');

            return false;
        }
        try {
            $invoice
                ->setRequestedCaptureCase($this->apiConfig->getCaptureCase())
                ->addComment(
                    __(
                        'Invoice automatically created by '
                        . 'Riskified when order was approved'
                    ),
                    false,
                    false
                );
            
            $this->state->emulateAreaCode(
                'adminhtml',
                [$invoice, 'register']
            );
        } catch (\Exception $e) {
            $this->logger->addInfo("Error creating invoice: " . $e->getMessage());
            return false;
        }
        try {
            $invoice->save();
            $invoice->getOrder()->save();
        } catch (\Exception $e) {
            $this->logger->addCritical(
                'Error creating transaction: ' . $e->getMessage()
            );

            return false;
        }
        $this->logger->addInfo("Transaction saved");
    }

    /**
     * Method checks if observer can be run
     *
     * @return bool
     */
    private function canRun()
    {
        if (!$this->apiConfig->isAutoInvoiceEnabled()) {
            return false;
        }
        if (!$this->apiConfig->isEnabled()) {
            return false;
        }

        return true;
    }
}
