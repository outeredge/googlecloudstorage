<?php
/**
 * Bootstrap for unit tests running inside a Magento project or
 * a standalone module checkout with a composer autoloader.
 */
foreach ([
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../../../autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
] as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        break;
    }
}
