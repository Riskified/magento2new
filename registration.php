<?php
include_once BP . '/vendor/riskified/php_sdk/src/Riskified/autoloader.php';
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Riskified_Decider',
    __DIR__
);
