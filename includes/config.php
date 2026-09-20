<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('CONFIG_FILE', APP_ROOT . '/config/config.php');
define('INSTALL_LOCK', APP_ROOT . '/config/install.lock');

function app_is_installed(): bool
{
    return file_exists(CONFIG_FILE) && file_exists(INSTALL_LOCK);
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        if (!file_exists(CONFIG_FILE)) {
            throw new RuntimeException('Application is not installed yet.');
        }
        $config = require CONFIG_FILE;
    }
    return $config;
}
