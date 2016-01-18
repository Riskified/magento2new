<?php
namespace Riskified\Decider\Plugin\Widget;

class Context
{
    public function afterGetButtonList(
        \Magento\Backend\Block\Widget\Context $subject,
        $buttonList,
        \Riskified\Decider\Api\Config $config
    )
    {
        if(!$config->isEnabled()) {
            return;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $request = $objectManager->get('Magento\Framework\App\Action\Context')->getRequest();
        if($request->getFullActionName() == 'sales_order_view'){
            $buttonList->add(
                'custom_button',
                [
                    'label' => __('Submit to Riskified'),
                    'onclick' => 'setLocation(\'' . $this->getCustomUrl() . '\')',
                    'sort_order' => 100
                ]
            );
        }

        return $buttonList;
    }

    public function getCustomUrl()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $urlManager = $objectManager->get('Magento\Framework\App\Action\Context')->getUrl();
        $request = $objectManager->get('Magento\Framework\App\Action\Context')->getRequest();
        return $urlManager->getUrl('riskified/riskified/send', array('order_id' => $request->getParam('order_id')));
    }
}