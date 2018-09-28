<?php

namespace Riskified\Decider\Observer;

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
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
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

        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $observer->getOrder();

        if (!$order || !$order->getId()) {
            return false;
        }
        $this->logger->addInfo(
            sprintf(
                __('Auto-invoicing  order #%s'),
                $order->getIncrementId()
            )
        );

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
            $this->logger->addInfo(
                __('Cannot create an invoice without products')
            );

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
            $this->logger->addInfo(
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
            $this->logger->addCritical(
                sprintf(
                    __('Error creating transaction: %s'),
                    $e->getMessage()
                )
            );

            return false;
        }
        $this->logger->addInfo(
            __("Transaction saved")
        );
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
