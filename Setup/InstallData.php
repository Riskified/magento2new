<?php

namespace Riskified\Decider\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        $setup->startSetup();

        $data = [];
        $statuses = [
            'riskified_holded' => __('Under Review (Riskified)'),
            'riskified_trans_error' => __('Transport Error (Riskified)'),
            'riskified_declined' => __('Declined (Riskified)'),
            'riskified_approved' => __('Approved (Riskified)'),
        ];
        foreach ($statuses as $code => $info) {
            $data[] = ['status' => $code, 'label' => $info];
        }
        $setup->getConnection()
            ->insertArray($setup->getTable('sales_order_status'), ['status', 'label'], $data);

        $stateData = [];
        $stateData[] = [
            'status' => 'riskified_approved',
            'state' => 'holded',
            'is_default' => 0,
            'visible_on_front' => 0
        ];
        $stateData[] = [
            'status' => 'riskified_declined',
            'state' => 'canceled',
            'is_default' => 0,
            'visible_on_front' => 0
        ];
        $stateData[] = [
            'status' => 'riskified_trans_error',
            'state' => 'holded',
            'is_default' => 0,
            'visible_on_front' => 0
        ];
        $stateData[] = [
            'status' => 'riskified_declined',
            'state' => 'holded', 'is_default' => 0,
            'visible_on_front' => 0
        ];
        $stateData[] = [
            'status' => 'riskified_holded',
            'state' => 'holded', 'is_default' => 0,
            'visible_on_front' => 0
        ];
        $stateData[] = [
            'status' => 'riskified_approved',
            'state' => 'processing', 'is_default' => 0,
            'visible_on_front' => 0
        ];

        $setup->getConnection()
            ->insertArray(
                $setup->getTable('sales_order_status_state'),
                ['status', 'state', 'is_default', 'visible_on_front'],
                $stateData
            );

        $setup->endSetup();
    }
}