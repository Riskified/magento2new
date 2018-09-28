<?php

namespace Riskified\Decider\Plugin\Widget;

use Magento\Framework\App\Action\Context as ActionContext;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;

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
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Context constructor.
     *
     * @param ActionContext $context
     * @param Registry $registry
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ActionContext $context,
        Registry $registry,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->context = $context;
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
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
    ) {
        $request = $this->context->getRequest();
        if ($request->getFullActionName() == 'sales_order_view') {
            if ($this->registry->registry('current_order')->getState() != 'canceled'
                && $this->scopeConfig->getValue('riskified/riskified_general/enabled')
            ) {
                $buttonList->add(
                    'send_to_riskified',
                    [
                        'label' => __('Submit to Riskified'),
                        'onclick' => 'setLocation(\'' . $this->getCustomUrl() . '\')',
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
            array('order_id' => $this->context->getRequest()->getParam('order_id'))
        );
    }
}
