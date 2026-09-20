<?php
declare(strict_types=1);

$pageTitle = 'Migrations';
require_once __DIR__ . '/_layout_start.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/migrator.php';

$migrator = new Migrator(db(), APP_ROOT . '/migrations');
$notices = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'migrate') {
            $ran = $migrator->migrate();
            $notices[] = $ran ? ('รัน migration สำเร็จ ' . count($ran) . ' รายการ: ' . implode(', ', $ran)) : 'ไม่มี migration ที่ต้องรัน';
        } elseif ($action === 'rollback') {
            $rolledBack = $migrator->rollbackLastBatch();
            $notices[] = $rolledBack ? ('ย้อนกลับสำเร็จ ' . count($rolledBack) . ' รายการ: ' . implode(', ', $rolledBack)) : 'ไม่มี batch ให้ย้อนกลับ';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$status = $migrator->status();
?>
<div class="card">
  <h2 style="margin-top:0">Migrations</h2>
  <p style="color:#666;font-size:14px">จัดการโครงสร้างฐานข้อมูล เพิ่มไฟล์ migration ใหม่ในโฟลเดอร์ <code>/migrations</code> แล้วกด "รัน migration ที่ค้างอยู่"</p>

  <?php foreach ($notices as $n): ?><div class="notice"><?php echo h($n); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="errors"><?php echo h($e); ?></div><?php endforeach; ?>

  <form method="post" style="margin-bottom:20px">
    <input type="hidden" name="action" value="migrate">
    <button class="btn" type="submit">รัน migration ที่ค้างอยู่</button>
    <button class="btn btn-danger" type="submit" form="rollback-form" style="margin-left:8px">ย้อนกลับ batch ล่าสุด</button>
  </form>
  <form id="rollback-form" method="post" style="display:none">
    <input type="hidden" name="action" value="rollback">
  </form>

  <table>
    <thead>
      <tr><th>Migration</th><th>สถานะ</th><th>Batch</th><th>เวลาที่รัน</th></tr>
    </thead>
    <tbody>
      <?php foreach ($status as $row): ?>
        <tr>
          <td><?php echo h($row['migration']); ?></td>
          <td>
            <?php if ($row['status'] === 'applied'): ?>
              <span class="badge badge-applied">applied</span>
            <?php else: ?>
              <span class="badge badge-pending">pending</span>
            <?php endif; ?>
          </td>
          <td><?php echo $row['batch'] ?? '-'; ?></td>
          <td><?php echo h($row['applied_at'] ?? '-'); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($status)): ?>
        <tr><td colspan="4" style="text-align:center;color:#999">ไม่พบไฟล์ migration</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/_layout_end.php'; ?>
