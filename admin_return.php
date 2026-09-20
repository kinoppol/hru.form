<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/auth.php';

site_start_session();

if (!site_check() || !site_current_user_is_admin_owned()) {
    header('Location: admin/login.php');
    exit;
}

$stmt = db()->prepare('
    SELECT u.admin_id, a.username
    FROM users u
    JOIN admin_users a ON a.id = u.admin_id
    WHERE u.id = ?
');
$stmt->execute([site_user_id()]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: admin/login.php');
    exit;
}

session_write_close();

auth_start_session();
session_regenerate_id(true);
$_SESSION['admin_id']       = (int) $row['admin_id'];
$_SESSION['admin_username'] = $row['username'];

header('Location: admin/index.php');
exit;
