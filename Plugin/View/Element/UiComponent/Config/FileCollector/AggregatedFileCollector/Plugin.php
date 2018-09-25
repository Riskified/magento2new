<?php

namespace Riskified\Decider\Plugin\View\Element\UiComponent\Config\FileCollector\AggregatedFileCollector;

use Magento\Framework\Filesystem;
use Magento\Framework\Registry as CoreRegistry;
use Magento\Framework\View\Element\UiComponent\Config\FileCollector\AggregatedFileCollector;
use Riskified\Decider\Model\Config as ModuleConfig;

/**
 * Riskified Decider view element ui component config aggregated file collector.
 *
 * @category Riskified
 * @package  Riskified_Decider
 * @author   Piotr Pierzak <piotrek.pierzak@gmail.com>
 */
class Plugin
{
    /**
     * Core registry object.
     *
     * @var CoreRegistry
     */
    protected $coreRegistry;

    /**
     * Object constructor.
     *
     * @param CoreRegistry $coreRegistry Core registry object.
     */
    public function __construct(
        CoreRegistry $coreRegistry
    ) {
        $this->coreRegistry = $coreRegistry;
    }

    /**
     * Plugin for collectFiles method. For magento version lower than 2.1
     * replace listingToolbar tag to container in sales_order_grid layout update.
     *
     * @param AggregatedFileCollector $subject Subject object.
     * @param array                   $result  Result array.
     *
     * @return array
     */
    public function afterCollectFiles(
        AggregatedFileCollector $subject,
        array $result
    ) {
        $fileKey = $this->coreRegistry
            ->registry(ModuleConfig::REGISTRY_SALES_ORDER_GRID_FILE_INDEX);

        if ($fileKey === null) {
            return $result;
        }
        if (empty($result[$fileKey])) {
            return $result;
        }

        $this->coreRegistry
            ->unregister(ModuleConfig::REGISTRY_SALES_ORDER_GRID_FILE_INDEX);

        $result[$fileKey] = str_replace(
            'listingToolbar',
            'container',
            $result[$fileKey]
        );

        return $result;
    }
}
