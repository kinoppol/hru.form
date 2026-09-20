<?php
declare(strict_types=1);
// Expects optional $pageTitle and $screenLabel to be set before include.
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?><?php echo h(app_name()); ?></title>
<link rel="stylesheet" href="<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>assets/css/app.css">
</head>
<body>
<header class="nav">
  <span class="nav-brand" <?php if (!isset($navBrand)): ?>onclick="location.href='<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>index.php'"<?php else: ?>style="cursor:default"<?php endif; ?>><?php echo isset($navBrand) ? h($navBrand) : h(app_name()); ?></span>
  <?php if (isset($screenLabel)): ?><span class="tag tag-outline"><?php echo h($screenLabel); ?></span><?php endif; ?>
  <span class="nav-spacer"></span>
  <?php if (site_check()): ?>
    <span class="text-muted" style="font-size:13px"><?php echo h(site_user_name() ?? ''); ?></span>
    <?php if (site_current_user_is_admin_owned()): ?>
      <a href="<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>admin_return.php">กลับสู่ระบบผู้ดูแล</a>
    <?php endif; ?>
    <a href="<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>dashboard.php">แดชบอร์ด</a>
    <a href="<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>logout.php">ออกจากระบบ</a>
  <?php else: ?>
    <a href="<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>login.php">เข้าสู่ระบบ</a>
    <button type="button" class="btn btn-primary" onclick="location.href='<?php echo isset($assetPrefix) ? h($assetPrefix) : ''; ?>signup.php'">เริ่มใช้งานฟรี</button>
  <?php endif; ?>
</header>
<main>
