<?php

namespace Riskified\Decider\Model\Observer;

use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order as OrderEntity;
use Magento\Sales\Model\Service\InvoiceService;
use Riskified\Decider\Model\Api\Config;
use Riskified\Decider\Model\Api\Order as OrderApi;
use Riskified\Decider\Model\Api\Order\Log;
use Riskified\Decider\Model\Logger\Order;

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
     * State class used to emulate admin scope during invoice creation.
     *
     * @var State
     */
    private $state;

    /**
     * Order repository class.
     *
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Invoice repository class.
     *
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * AutoInvoice constructor.
     *
     * @param Log                  $apiOrderLogger
     * @param Order                $logger
     * @param Config               $apiConfig
     * @param OrderApi             $orderApi
     * @param InvoiceService       $invoiceService
     * @param Context              $context
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
     * @param \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        Log $apiOrderLogger,
        Order $logger,
        Config $apiConfig,
        OrderApi $orderApi,
        InvoiceService $invoiceService,
        Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        Registry $registry
    ) {
        $this->logger = $logger;
        $this->context = $context;
        $this->apiOrder = $orderApi;
        $this->apiConfig = $apiConfig;
        $this->apiOrderLogger = $apiOrderLogger;
        $this->invoiceService = $invoiceService;
        $this->state = $context->getAppState();
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->registry = $registry;
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
        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $observer->getOrder();

        if (!$this->canRun($order)) {
            return false;
        }

        if (!$order || !$order->getId()) {
            return false;
        }

        $this->registry->register("riskified-order", $order, true);

        $this->logger->info(
            sprintf(
                __('Auto-invoicing order #%s'),
                $order->getIncrementId()
            )
        );

        if ($order->getPayment()->getMethod() == "flxpayment") {
            $this->logger->info('Order cannot be invoiced. Flexiti process.');

            return;
        }

        if (!$order->canInvoice()
            || ($order->getState() != OrderEntity::STATE_PROCESSING && $order->getState() != "pending_payment")
        ) {
            $this->logger->info('Order cannot be invoiced');
            if ($this->apiConfig->isLoggingEnabled()) {
                $this->apiOrderLogger->logInvoice($order);
            }

            $this->orderRepository->save($order);
            $this->logger->info("Saved order #{$order->getIncrementId()}");
            return false;
        }

        $invoice = $this->state->emulateAreaCode(
            'adminhtml',
            [$this->invoiceService, 'prepareInvoice'],
            [$order]
        );

        if (!$invoice->getTotalQty()) {
            $this->logger->info(
                __('Cannot create an invoice without products')
            );

            return false;
        }
        try {
            $invoice
                ->setRequestedCaptureCase($this->apiConfig->getCaptureCase())
                ->addComment(
                    __('Invoice automatically created by Riskified when order was approved'),
                    false,
                    false
                );

            $this->state->emulateAreaCode(
                'adminhtml',
                [$invoice, 'register']
            );
        } catch (\Exception $e) {
            $this->logger->info(
                sprintf(
                    __("Error creating invoice: %s"),
                    $e->getMessage()
                )
            );
            return false;
        }

        try {
            $this->invoiceRepository->save($invoice);
            $this->orderRepository->save($invoice->getOrder());
        } catch (\Exception $e) {
            $this->logger->log(
                'critical',
                sprintf(
                    __('Error creating transaction: %s'),
                    $e->getMessage()
                )
            );

            return false;
        }
        $this->logger->info(
            __("Transaction saved")
        );
    }

    /**
     * Method checks if observer can be run
     *
     * @return bool
     */
    private function canRun(OrderInterface $order)
    {
        if (!$this->apiConfig->isAutoInvoiceEnabled()) {
            return false;
        }
        if (!$this->apiConfig->isEnabled($order->getStoreId())) {
            return false;
        }

        return true;
    }
}
