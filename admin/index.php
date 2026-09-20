<?php
declare(strict_types=1);

$pageTitle = 'แดชบอร์ด';
require_once __DIR__ . '/_layout_start.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/migrator.php';

$migrator = new Migrator(db(), APP_ROOT . '/migrations');
$pendingCount = count($migrator->pendingMigrations());
$config = app_config();
?>
<div class="card">
  <h2 style="margin-top:0">ยินดีต้อนรับ, <?php echo h(auth_current_username() ?? ''); ?></h2>
  <p>ระบบ: <strong><?php echo h($config['app']['name']); ?></strong></p>
  <?php if ($pendingCount > 0): ?>
    <div class="notice">มี migration ที่ยังไม่ได้รัน <?php echo $pendingCount; ?> รายการ &mdash; <a href="migrations.php">จัดการ migrations</a></div>
  <?php else: ?>
    <div class="notice">โครงสร้างฐานข้อมูลเป็นปัจจุบันแล้ว</div>
  <?php endif; ?>
</div>
<div class="card">
  <div class="card-title" style="font-weight:700;margin-bottom:6px">ฟอร์มและแบบทดสอบ</div>
  <p style="margin:0 0 12px;color:#666;font-size:14px">สร้างและจัดการฟอร์ม/แบบทดสอบของคุณเองในฐานะผู้ดูแลระบบ</p>
  <a class="btn" href="forms.php">จัดการฟอร์ม</a>
</div>
<?php require_once __DIR__ . '/_layout_end.php'; ?>
