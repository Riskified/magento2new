<?php

namespace Riskified\Decider\Plugin\View\Layout\File\Collector\Aggregated;

use Closure;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry as CoreRegistry;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\Layout\File\Collector\Aggregated;
use Riskified\Decider\Model\Config as ModuleConfig;

/**
 * Riskified Decider view layout file collector aggregated plugin.
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
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Product meta data object.
     *
     * @var ProductMetadata
     */
    protected $productMetadata;

    /**
     * Object constructor.
     *
     * @param CoreRegistry    $coreRegistry    Core registry object.
     * @param Filesystem      $filesystem      File system object.
     * @param ProductMetadata $productMetadata Product meta data object.
     */
    public function __construct(
        CoreRegistry $coreRegistry,
        Filesystem $filesystem,
        ProductMetadata $productMetadata
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->filesystem = $filesystem;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Plugin for getFiles method. Get order archive layout update file index
     * and save it in registry to be able to recognize it in further processing.
     * It will be applied for magento version lower than 2.1.
     *
     * @param Aggregated     $subject  Subject object.
     * @param Closure        $proceed  Closure object.
     * @param ThemeInterface $theme    Theme object.
     * @param string         $filePath File path.
     *
     * @return array
     */
    public function aroundGetFiles(
        Aggregated $subject,
        Closure $proceed,
        ThemeInterface $theme,
        $filePath
    ) {
        $files = $proceed($theme, $filePath);
        if ($filePath !== 'sales_order_grid.xml') {
            return $files;
        }

        $version = $this->productMetadata->getVersion();
        if (version_compare($version, '2.1') >= 0) {
            return $files;
        }

        $fileReader = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);

        foreach ($files as $index => $file) {
            if ($file->getModule() !== 'Riskified_Decider') {
                continue;
            }

            $filePath = $fileReader->getRelativePath($file->getFilename());
            $fileKey = sprintf('%x', crc32($filePath));

            $this->coreRegistry->register(
                ModuleConfig::REGISTRY_SALES_ORDER_GRID_FILE_INDEX,
                $fileKey
            );
        }

        return $files;
    }
}
