<?php

namespace Riskified\Decider\Observer\Order;

use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Service\InvoiceService;
use Riskified\Decider\Api\Config;
use Riskified\Decider\Api\Order as OrderApi;
use Riskified\Decider\Api\Order\Log;
use Riskified\Decider\Logger\Order;


class Declined implements ObserverInterface {
    /**
     * Module main logger class.
     *
     * @var Order
     */
    protected $logger;

    /**
     * Module api class.
     *
     * @var OrderApi
     */
    protected $apiOrder;

    /**
     * Api logger.
     *
     * @var Log
     */
    protected $apiOrderLogger;

    /**
     * Module config.
     *
     * @var Config
     */
    protected $apiConfig;


    /**
     * Magento's invoice service.
     *
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * Context class.
     *
     * @var Context
     */
    protected $context;

    /**
     * Object Manager class.
     *
     * @var ObjectManagerFactory
     */
    protected $objectManager;

    /**
     * State class used to emulate admin scope during invoice creation.
     *
     * @var State
     */
    protected $state;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    private $inlineTranslation;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

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
        ObjectManagerFactory $objectManagerFactory,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Escaper $escaper
    ) {
        $this->logger = $logger;
        $this->context = $context;
        $this->apiOrder = $orderApi;
        $this->apiConfig = $apiConfig;
        $this->apiOrderLogger = $apiOrderLogger;
        $this->invoiceService = $invoiceService;
        $this->objectManager = $objectManagerFactory;
        $this->state = $context->getAppState();
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->escaper = $escaper;
    }

    /**
     * Observer execute
     * 
     * @param Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();
        $this->order = $order;

        if (!$this->apiConfig->isDeclineNotificationEnabled()) {
            return $this;
        }

        $subject = $this->apiConfig->getDeclineNotificationSubject();
        $content = $this->apiConfig->getDeclineNotificationContent();

        $shortCodes = [
            "{{customer_name}}",
            "{{customer_firstname}}",
            "{{order_increment_id}}",
            "{{order_view_url}}",
            "{{products}}",
            "{{store_name}}",
        ];
        $formattedPayload = $this->getFormattedData($order);

        foreach ($shortCodes as $key => $value) {
            $subject = str_replace($value, $formattedPayload[$key], $subject);
            $content = str_replace($value, $formattedPayload[$key], $content);
        }

        try {
            if ($content == "") {
                throw new \Exception("Email content is empty");
            }

            if ($subject == "") {
                throw new \Exception("Email subject is empty");
            }

            $this->inlineTranslation->suspend();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('send_email_email_template') // this code we have mentioned in the email_templates.xml
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND, // this is using frontend area to get the template file
                        'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars(
                    [
                        "content" => $content,
                        "subject" => $subject,
                    ]
                )
                ->setFrom(
                    [
                        "email" => $this->apiConfig->getDeclineNotificationSenderEmail(),
                        "name" => $this->apiConfig->getDeclineNotificationSenderName(),
                    ]
                )
                ->addTo($order->getCustomerEmail(), $order->getCustomerName())
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $fileLog = sprintf(
                __("Declination email was sent to customer %s (%s) for order #%s"),
                $order->getCustomerName(),
                $order->getCustomerEmail(),
                $order->getIncrementId()
            );

            $orderComment = sprintf(
                __("Declination email was sent to customer %s (%s)"),
                $order->getCustomerName(),
                $order->getCustomerEmail()
            );

            $this->logger->info($fileLog);

            $order
                ->addStatusHistoryComment($orderComment)
                ->setIsCustomerNotified(true);
            $order->save();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * @param $order
     * @return array
     */
    private function getFormattedData($order)
    {
        $products = [];

        foreach ($order->getAllItems() as $item) {
            $products[] = $item->getName();
        }

        $data = [
            $order->getCustomerName(),
            $order->getCustomerFirstname(),
            $order->getIncrementId(),
            join(', ', $products),
        ];

        return $data;
    }
}