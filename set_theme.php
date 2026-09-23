<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
site_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$formId = (int) ($_POST['form_id'] ?? 0);
$hex = (string) ($_POST['hex'] ?? '');
$return = $_POST['return'] ?? 'dashboard';

if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
    $stmt = db()->prepare('UPDATE forms SET theme_color = ? WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$hex, $formId, site_user_id()]);
}

if ($return === 'form_edit') {
    header('Location: form_edit.php?id=' . $formId);
} else {
    header('Location: dashboard.php');
}
exit;
