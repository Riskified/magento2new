<?php
namespace Riskified\Decider\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('sales_order'),
                'riskified_cart_token',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'length' => '255',
                    'comment' => 'Cart token that is sent to riskified',
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.2.0', '<')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('riskified_decision_queue')
            )->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity ID'
            )->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Order ID'
            )->addColumn(
                'decision',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Decision'
            )->addColumn(
                'description',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Description'
            )->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Created At'
            );
            $installer->getConnection()->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.2.1', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('riskified_decision_queue'),
                'attempts_count',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    'nullable' => false,
                    'default' => 0,
                    'length' => 1,
                    'comment' => "Number of attempts allowed to be processed",
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.2.2', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('sales_order'),
                'riskified_admin_notified',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    'nullable' => false,
                    'length' => 1,
                    'default' => 0,
                    'comment' => 'Prevent processing order on Riskified decision',
                ]
            );
        }

        $installer->endSetup();
    }
}
