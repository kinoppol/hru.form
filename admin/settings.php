<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout_start.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['app_name'] ?? ''));
    if (mb_strlen($name) > 50) { $name = mb_substr($name, 0, 50); }
    if ($name === '') {
        db()->prepare("DELETE FROM settings WHERE `key` = 'app_name'")->execute();
    } else {
        db()->prepare("INSERT INTO settings (`key`, `value`) VALUES ('app_name', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$name]);
    }
    header('Location: settings.php?saved=1');
    exit;
}
if (isset($_GET['saved'])) { $notice = 'บันทึกแล้ว'; }
$current = db()->query("SELECT `value` FROM settings WHERE `key` = 'app_name'")->fetchColumn();
?>
<div class="card">
  <h2>ตั้งค่าระบบ</h2>
  <?php if ($notice !== ''): ?><p><strong><?php echo h($notice); ?></strong></p><?php endif; ?>
  <form method="post">
    <div class="field">
      <label for="app_name">ชื่อระบบ</label>
      <input id="app_name" name="app_name" maxlength="50" placeholder="Noema" value="<?php echo h(is_string($current) ? $current : ''); ?>">
    </div>
    <p style="font-size:13px;opacity:.6">เว้นว่างเพื่อใช้ค่าเริ่มต้น (Noema)</p>
    <button class="btn" type="submit">บันทึก</button>
  </form>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
