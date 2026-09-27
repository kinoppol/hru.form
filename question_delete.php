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
$questionId = (int) ($_POST['question_id'] ?? 0);

$stmt = db()->prepare('SELECT f.id FROM forms f WHERE f.id = ? AND f.user_id = ? AND f.deleted_at IS NULL');
$stmt->execute([$formId, site_user_id()]);
if ($stmt->fetch()) {
    $del = db()->prepare('DELETE FROM questions WHERE id = ? AND form_id = ?');
    $del->execute([$questionId, $formId]);
}

header('Location: form_edit.php?id=' . $formId);
exit;
