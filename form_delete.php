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

// Soft delete only: the form, its questions and responses stay in the database
// and can be restored from the trash view.
$formId = (int) ($_POST['form_id'] ?? 0);
if (($_POST['action'] ?? 'delete') === 'restore') {
    db()->prepare('UPDATE forms SET deleted_at = NULL WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL')->execute([$formId, site_user_id()]);
    header('Location: dashboard.php?restored=1');
} else {
    db()->prepare('UPDATE forms SET deleted_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL')->execute([$formId, site_user_id()]);
    header('Location: dashboard.php?deleted=1');
}
exit;
