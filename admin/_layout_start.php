<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!app_is_installed()) {
    header('Location: ../install.php');
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
auth_require_login();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?>ผู้ดูแลระบบ - HRU Form</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="topbar">
  <span class="brand"><span class="dot"></span>HRU Form &mdash; ผู้ดูแลระบบ</span>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="forms.php">จัดการฟอร์ม</a>
    <a href="settings.php">ตั้งค่าระบบ</a>
    <a href="migrations.php">Migrations</a>
    <a href="logout.php">ออกจากระบบ (<?php echo h(auth_current_username() ?? ''); ?>)</a>
  </nav>
</div>
<div class="wrap">
