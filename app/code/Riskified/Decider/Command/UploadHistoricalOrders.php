<?php
namespace Riskified\Decider\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Riskified\Common\Riskified;
use Riskified\Common\Env;
use Riskified\Common\Validations;
use Riskified\Common\Signature;
use Riskified\OrderWebhook\Model;
use Riskified\OrderWebhook\Transport;
use Riskified\OrderWebhook\Transport\CurlTransport;

class UploadHistoricalOrders extends Command
{
    protected $_scopeConfig;
    protected $_orderRepository;
    protected $_searchCriteriaBuilder;
    protected $_orderHelper;
    protected $_transport;
    protected $_totalUploaded = 0;
    protected $_currentPage = 1;
    protected $_orders;

    const BATCH_SIZE = 10;

    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteria $searchCriteriaBuilder
    ) {
        $state->setAreaCode('adminhtml');

        $this->_scopeConfig             = $scopeConfig;
        $this->_orderRepository         = $orderRepository;
        $this->_searchCriteriaBuilder   = $searchCriteriaBuilder;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_orderHelper = $objectManager->get('\Riskified\Decider\Api\Order\Helper');

        $this->_transport = new CurlTransport(new Signature\HttpDataSignature());
        $this->_transport->timeout = 15;

        parent::__construct();
    }
    protected function configure()
    {
        $this->setName('riskified:sync:historical-orders');
        $this->setDescription('Send your historical orders to riskified backed');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $authToken = $this->_scopeConfig->getValue('riskified/riskified/key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $env = constant('\Riskified\Common\Env::' . $this->_scopeConfig->getValue('riskified/riskified/env'));
        $domain = $this->_scopeConfig->getValue('riskified/riskified/domain');

        $output->writeln("Riskified auth token: $authToken \n");
        $output->writeln("Riskified shop domain: $domain \n");
        $output->writeln("Riskified target environment: $env \n");
        $output->writeln("*********** \n");


        Riskified::init($domain, $authToken, $env, Validations::SKIP);

        $orders = $this->_getEntireCollection();
        $total_count = count($orders);

        $output->writeln("Starting to upload orders, total_count: $total_count \n");
        $this->_getCollection();
        while ($this->_totalUploaded < $total_count) {
            try {
                $this->_postOrders();
                $this->_totalUploaded += count($this->_orders);
                $this->_currentPage++;
                $output->writeln("Uploaded " .
                    $this->_totalUploaded .
                    " of " .
                    $total_count
                    ." orders\n");

                $this->_getCollection();
            } catch (\Exception $e) {
                $output->writeln("<error>".$e->getMessage()."</error> \n");
                exit(1);
            }
        }
    }

    protected function _getEntireCollection() {
        $orderResult = $this->_orderRepository->getList($this->_searchCriteriaBuilder);
        return $orderResult->getItems();
    }

    protected function _getCollection() {
        $this->_searchCriteriaBuilder
            ->setPageSize(self::BATCH_SIZE)
            ->setCurrentPage($this->_currentPage);
        $orderResult = $this->_orderRepository->getList($this->_searchCriteriaBuilder);
        $this->_orders = $orderResult->getItems();
    }

    protected function _postOrders() {
        if (!$this->_scopeConfig->getValue('riskified/riskified_general/enabled')) {
            return;
        }
        $orders = array();

        foreach ($this->_orders as $model) {
            $orders[] = $this->_prepareOrder($model);
        }
        $this->_transport->sendHistoricalOrders($orders);
    }

    protected function _prepareOrder($model) {
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
            'vendor_id' => $model->getStoreId(),
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