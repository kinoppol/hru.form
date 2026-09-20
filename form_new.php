<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$stmt = db()->prepare('
    INSERT INTO forms (user_id, title, status, share_token, personal_fields_json)
    VALUES (?, ?, ?, ?, ?)
');
$stmt->execute([
    site_user_id(),
    'ฟอร์มใหม่',
    'draft',
    generate_share_token(),
    json_encode(default_personal_fields(), JSON_UNESCAPED_UNICODE),
]);

$formId = (int) db()->lastInsertId();
header('Location: form_edit.php?id=' . $formId);
exit;
