<?php

namespace Riskified\Decider\Plugin\Widget;

use Magento\Framework\App\Action\Context as ActionContext;
use Magento\Framework\Registry;
use Riskified\Decider\Model\Api\Config;

class Context
{
    /**
     * @var ActionContext
     */
    private $context;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Config
     */
    private $config;

    /**
     * Context constructor.
     *
     * @param ActionContext $context
     * @param Registry $registry
     * @param Config $scopeConfig
     */
    public function __construct(
        ActionContext $context,
        Registry $registry,
        Config $config
    ) {
        $this->context = $context;
        $this->registry = $registry;
        $this->config = $config;
    }

    /**
     * @param \Magento\Backend\Block\Widget\Context $subject
     * @param $buttonList
     *
     * @return mixed
     */
    public function afterGetButtonList(
        \Magento\Backend\Block\Widget\Context $subject,
        $buttonList
    ): mixed {
        $request = $this->context->getRequest();
        if ($request->getFullActionName() == 'sales_order_view' && $this->config->isEnabled()) {
            $order = $this->registry->registry('current_order');
            $submitToRiskifiedStatuses = ['canceled', 'holded'];
            if (!in_array($order->getState(), $submitToRiskifiedStatuses)) {
                $buttonList->add(
                    'send_to_riskified',
                    [
                        'label' => __('Submit to Riskified'),
                        'onclick' => 'setLocation(\'' . $this->getCustomUrl() . '\')',
                        'sort_order' => 100
                    ]
                );
            }
            if ($order->getState() == 'holded') {
                $buttonList->add(
                    'fetch_status',
                    [
                        'label' => __('Get Riskified Decision'),
                        'onclick' => 'setLocation(\'' . $this->getDecisionUrl() . '\')',
                        'sort_order' => 100
                    ]
                );
            }
        }

        return $buttonList;
    }

    /**
     * @return string
     */
    public function getCustomUrl()
    {
        return $this->context->getUrl()->getUrl(
            'riskified/riskified/send',
            ['order_id' => $this->context->getRequest()->getParam('order_id')]
        );
    }
    public function getDecisionUrl()
    {
        return $this->context->getUrl()->getUrl(
            'riskified/riskified/decision',
            ['order_id' => $this->context->getRequest()->getParam('order_id')]
        );
    }
}
