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
<style>
  body{font-family:-apple-system,Segoe UI,Tahoma,sans-serif;background:#f4f5f7;margin:0;color:#1f2430}
  .topbar{background:#1f2430;color:#fff;padding:14px 24px;display:flex;justify-content:space-between;align-items:center}
  .topbar a{color:#fff;text-decoration:none;margin-left:16px;font-size:14px;opacity:.85}
  .topbar a:hover{opacity:1}
  .brand{font-weight:700}
  .wrap{max-width:960px;margin:0 auto;padding:32px 16px}
  .card{background:#fff;border-radius:10px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:20px}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid #eee}
  th{color:#666;font-weight:600;font-size:12px;text-transform:uppercase}
  .badge{padding:3px 9px;border-radius:99px;font-size:12px;font-weight:600}
  .badge-applied{background:#dcfce7;color:#15803d}
  .badge-pending{background:#fef9c3;color:#a16207}
  .btn{display:inline-block;background:#2f6fed;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:14px;cursor:pointer;text-decoration:none}
  .btn:hover{background:#255bd0}
  .btn-danger{background:#dc2626}
  .btn-danger:hover{background:#b91c1c}
  .notice{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:14px}
  .errors{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:14px}
</style>
</head>
<body>
<div class="topbar">
  <span class="brand">HRU Form &mdash; ผู้ดูแลระบบ</span>
  <div>
    <a href="index.php">แดชบอร์ด</a>
    <a href="migrations.php">Migrations</a>
    <a href="logout.php">ออกจากระบบ (<?php echo h(auth_current_username() ?? ''); ?>)</a>
  </div>
</div>
<div class="wrap">
