<?php
namespace Riskified\Decider\Plugin\Widget;

class Context
{
    public function afterGetButtonList(
        \Magento\Backend\Block\Widget\Context $subject,
        $buttonList
    )
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $request = $objectManager->get('Magento\Framework\App\Action\Context')->getRequest();

        if($request->getFullActionName() == 'sales_order_view'){
			$registry = $objectManager->get('Magento\Framework\Registry');
			$scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
			
			if($registry->registry('current_order')->getState() != 'canceled' && $scopeConfig->getValue('riskified/riskified_general/enabled')) {
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

    public function getCustomUrl()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $urlManager = $objectManager->get('Magento\Framework\App\Action\Context')->getUrl();
        $request = $objectManager->get('Magento\Framework\App\Action\Context')->getRequest();
        return $urlManager->getUrl('riskified/riskified/send', array('order_id' => $request->getParam('order_id')));
    }
}