<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

if (!app_is_installed()) {
    header('Location: install.php');
    exit;
}

header('Location: admin/login.php');
exit;
