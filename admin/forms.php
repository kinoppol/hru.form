<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!app_is_installed()) {
    header('Location: ../install.php');
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
auth_require_login();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/site_auth.php';

// Admins manage forms through the same dashboard/builder pages as regular
// users, via a users row auto-provisioned for this admin account.
site_login_as_admin((int) auth_current_id(), (string) auth_current_username());

header('Location: ../dashboard.php');
exit;
