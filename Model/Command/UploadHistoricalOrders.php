<?php

namespace Riskified\Decider\Model\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Riskified\Common\Riskified;
use Riskified\Common\Validations;
use Riskified\Common\Signature;
use Riskified\OrderWebhook\Model;
use Riskified\OrderWebhook\Transport\CurlTransport;
use Riskified\Decider\Model\Api\Order\Helper;

class UploadHistoricalOrders extends Command
{
    const BATCH_SIZE = 10;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_state;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteria
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var Helper
     */
    protected $_orderHelper;

    /**
     * @var CurlTransport
     */
    protected $_transport;

    /**
     * @var int
     */
    protected $_totalUploaded = 0;

    /**
     * @var int
     */
    protected $_currentPage = 1;

    /**
     * @var
     */
    protected $_orders;

    /**
     * UploadHistoricalOrders constructor.
     *
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteria $searchCriteriaBuilder
     * @param Helper $helper
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteria $searchCriteriaBuilder,
        Helper $helper
    ) {
        $this->_state = $state;

        $this->_scopeConfig             = $scopeConfig;
        $this->_orderRepository         = $orderRepository;
        $this->_searchCriteriaBuilder   = $searchCriteriaBuilder;

        $this->_orderHelper = $helper;

        $this->_transport = new CurlTransport(new Signature\HttpDataSignature());
        $this->_transport->timeout = 15;

        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('riskified:sync:historical-orders');
        $this->setDescription('Send your historical orders to riskified backed');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if (!$this->_state->getAreaCode()) {
            $this->_state->setAreaCode('adminhtml');
        }

        $authToken = $this->_scopeConfig->getValue('riskified/riskified/key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $env = constant('\Riskified\Common\Env::' . $this->_scopeConfig->getValue('riskified/riskified/env'));
        $domain = $this->_scopeConfig->getValue('riskified/riskified/domain');

        $output->writeln("Riskified auth token: $authToken \n");
        $output->writeln("Riskified shop domain: $domain \n");
        $output->writeln("Riskified target environment: $env \n");
        $output->writeln("*********** \n");


        Riskified::init($domain, $authToken, $env, Validations::SKIP);

        $fullOrderRepository = $this->getEntireCollection();
        $total_count = $fullOrderRepository->getSize();

        $output->writeln("Starting to upload orders, total_count: $total_count \n");
        $this->getCollection();
        while ($this->_totalUploaded < $total_count) {
            try {
                $this->postOrders();
                $this->_totalUploaded += count($this->_orders);
                $this->_currentPage++;
                $output->writeln("Uploaded " .
                    $this->_totalUploaded .
                    " of " .
                    $total_count
                    ." orders\n");

                $this->getCollection();
            } catch (\Exception $e) {
                $output->writeln("<error>".$e->getMessage()."</error> \n");
                exit(1);
            }
        }
    }

    /**
     * Retrieve prepared order collection for counting values
     *
     * @return \Magento\Sales\Api\Data\OrderSearchResultInterface
     */
    protected function getEntireCollection()
    {
        $orderResult = $this
            ->_orderRepository
            ->getList($this->_searchCriteriaBuilder);
        return $orderResult;
    }

    /**
     * Retrieve paginated collection
     *
     * @return void
     */
    protected function getCollection()
    {
        $this->_searchCriteriaBuilder
            ->setPageSize(self::BATCH_SIZE)
            ->setCurrentPage($this->_currentPage);
        $orderResult = $this->_orderRepository->getList($this->_searchCriteriaBuilder);
        $this->_orders = $orderResult->getItems();
    }

    /**
     * Sends orders to endpoint
     *
     * @return void
     */
    protected function postOrders()
    {
        if (!$this->_scopeConfig->getValue('riskified/riskified_general/enabled')) {
            return;
        }
        $orders = array();

        foreach ($this->_orders as $model) {
            $orders[] = $this->prepareOrder($model);
        }
        $this->_transport->sendHistoricalOrders($orders);
    }

    /**
     * @param Model\Order $model
     *
     * @return Model\Order
     */
    protected function prepareOrder($model)
    {
        $gateway = 'unavailable';
        if ($model->getPayment()) {
            $gateway = $model->getPayment()->getMethod();
        }

        $this->_orderHelper->setOrder($model);

        $order_array = array(
            'id' => $this->_orderHelper->getOrderOrigId(),
            'name' => $model->getIncrementId(),
            'email' => $model->getCustomerEmail(),
            'created_at' => $this->_orderHelper->formatDateAsIso8601($model->getCreatedAt()),
            'currency' => $model->getOrderCurrencyCode(),
            'updated_at' => $this->_orderHelper->formatDateAsIso8601($model->getUpdatedAt()),
            'gateway' => $gateway,
            'browser_ip' => $this->_orderHelper->getRemoteIp(),
            'note' => $model->getCustomerNote(),
            'total_price' => $model->getGrandTotal(),
            'total_discounts' => $model->getDiscountAmount(),
            'subtotal_price' => $model->getBaseSubtotalInclTax(),
            'discount_codes' => $this->_orderHelper->getDiscountCodes($model),
            'taxes_included' => true,
            'total_tax' => $model->getBaseTaxAmount(),
            'total_weight' => $model->getWeight(),
            'cancelled_at' => $this->_orderHelper->formatDateAsIso8601($this->_orderHelper->getCancelledAt()),
            'financial_status' => $model->getState(),
            'fulfillment_status' => $model->getStatus(),
            'vendor_id' => strval($model->getStoreId()),
            'vendor_name' => $model->getStoreName(),
        );

        $order = new Model\Order(array_filter($order_array, 'strlen'));
        $order->customer = $this->_orderHelper->getCustomer();
        $order->shipping_address = $this->_orderHelper->getShippingAddress();
        $order->billing_address = $this->_orderHelper->getBillingAddress();
        $order->payment_details = $this->_orderHelper->getPaymentDetails();
        $order->line_items = $this->_orderHelper->getLineItems();
        $order->shipping_lines = $this->_orderHelper->getShippingLines();

        return $order;
    }
}
