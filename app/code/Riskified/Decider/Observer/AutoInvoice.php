<?php
namespace Riskified\Decider\Observer;

use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Service\InvoiceService;
use Riskified\Decider\Api\Config;
use Riskified\Decider\Api\Order as OrderApi;
use Riskified\Decider\Api\Order\Log;
use Riskified\Decider\Logger\Order;
use Magento\Framework\App\State;

/**
 * Observer Auto Invoice Class
 * Creates invoice when order was approved in Riskified
 *
 * @category    Riskified
 * @package     Riskified_Decider
 */
class AutoInvoice implements ObserverInterface {
    /**
     * Module main logger class
     *
     * @var Order
     */
    protected $logger;

    /**
     * Module api class
     *
     * @var OrderApi
     */
    protected $apiOrder;

    /**
     * Api logger
     *
     * @var Log
     */
    protected $apiOrderLogger;

    /**
     * Module config
     *
     * @var Config
     */
    protected $apiConfig;


    /**
     * Magento's invoice service
     *
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * Context class
     *
     * @var Context
     */
    protected $context;

    /**
     * Object Manager class
     *
     * @var ObjectManagerFactory
     */
    protected $objectManager;

    /**
     * State class used to emulate admin scope during invoice creation
     *
     * @var State
     */
    protected $state;

    /**
     * AutoInvoice constructor.
     *
     * @param Log $apiOrderLogger
     * @param Order $logger
     * @param Config $apiConfig
     * @param OrderApi $orderApi
     * @param InvoiceService $invoiceService
     * @param Context $context
     * @param ObjectManagerFactory $objectManagerFactory
     * @param State $state
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
        $this->logger         = $logger;
        $this->context        = $context;
        $this->apiOrder       = $orderApi;
        $this->apiConfig      = $apiConfig;
        $this->apiOrderLogger = $apiOrderLogger;
        $this->invoiceService = $invoiceService;
        $this->objectManager  = $objectManagerFactory;
        $this->state  = $context->getAppState();
    }

    /**
     * Main method ran during event raise
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute( Observer $observer ) {
        if(!$this->canRun()) return false;

        $order = $observer->getOrder();

        if ( ! $order || ! $order->getId() ) {
            return false;
        }
        $this->logger->addInfo( "Auto-invoicing  order " . $order->getId() );

        if (
            ! $order->canInvoice()
            || $order->getState() != \Magento\Sales\Model\Order::STATE_PROCESSING
        ) {
            $this->logger->addInfo( "Order cannot be invoiced" );
            if ( $this->apiConfig->isLoggingEnabled() ) {
                $this->apiOrderLogger->logInvoice( $order );
            }

            return false;
        }

        $invoice = $this->state->emulateAreaCode(
            'adminhtml',
            array($this->invoiceService, 'prepareInvoice'),
            array($order)
        );

        if ( ! $invoice->getTotalQty() ) {
            $this->logger->addInfo( "Cannot create an invoice without products" );
            return;
        }
        try {
            $invoice
                ->setRequestedCaptureCase( $this->apiConfig->getCaptureCase() )
                ->addComment(
                    __( 'Invoice automatically created by Riskified when order was approved' ),
                    false,
                    false
                )
                ->register();

            $order->setStatus( 'riskified_approved' );
            $order->addStatusHistoryComment( __( "Reviewed and approved by Riskified" ),
                'riskified_approved' );
            $order->save();
        } catch ( \Exception $e ) {
            $this->logger->addInfo( "Error creating invoice: " . $e->getMessage() );

            return false;
        }
        try {
            $invoice->save();
            $invoice->getOrder()->save();
        } catch ( \Exception $e ) {
            $this->logger->addCritical( "Error creating transaction: " . $e->getMessage() );

            return false;
        }
        $this->logger->addInfo( "Transaction saved" );
    }

    /**
     * Method checks if observer can be run
     *
     * @return bool
     */
    protected function canRun() {
        if ( ! $this->apiConfig->isAutoInvoiceEnabled() ) {
            return false;
        }
        if ( ! $this->apiConfig->isEnabled() ) {
            return false;
        }

        return true;
    }
}
