<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_require_login();

$formId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM forms WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
$stmt->execute([$formId, site_user_id()]);
$form = $stmt->fetch();

if (!$form) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requireLogin = isset($_POST['require_login']) ? 1 : 0;
    $status = isset($_POST['publish']) ? 'published' : $form['status'];
    $accepting = isset($_POST['accepting_responses']) ? 1 : 0;
    $upd = db()->prepare('UPDATE forms SET require_login = ?, status = ?, accepting_responses = ? WHERE id = ? AND user_id = ?');
    $upd->execute([$requireLogin, $status, $accepting, $formId, site_user_id()]);
    header('Location: share.php?id=' . $formId . '&saved=1');
    exit;
}

$shareLink = app_base_url() . '/f.php?token=' . $form['share_token'];

$assetPrefix = '';
$screenLabel = 'แชร์ลิงก์';
$pageTitle = 'แชร์ลิงก์แบบฟอร์ม';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap-narrow">
  <div class="card elev-md" style="padding:24px">
    <div class="dialog-title">แชร์ลิงก์แบบฟอร์ม</div>
    <p class="text-muted">ส่งลิงก์นี้ให้กลุ่มเป้าหมายเพื่อตอบ "<?php echo h($form['title']); ?>"</p>

    <?php if (isset($_GET['saved'])): ?><div class="notice">บันทึกการตั้งค่าแล้ว</div><?php endif; ?>
    <?php if ($form['status'] !== 'published'): ?><div class="notice">ฟอร์มนี้ยังเป็นฉบับร่าง เผยแพร่ก่อนเพื่อให้ผู้อื่นตอบได้</div><?php endif; ?>
    <?php if ($form['status'] === 'published' && !($form['accepting_responses'] ?? 1)): ?><div class="notice">ฟอร์มนี้ปิดรับคำตอบอยู่ ผู้ที่เปิดลิงก์จะไม่สามารถตอบได้</div><?php endif; ?>

    <form method="post">
      <div style="display:flex;gap:8px;margin-bottom:14px">
        <input class="input" readonly value="<?php echo h($shareLink); ?>" onclick="this.select()">
        <button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText('<?php echo h($shareLink); ?>');this.textContent='คัดลอกแล้ว ✓'">คัดลอกลิงก์</button>
      </div>
      <label style="display:flex;align-items:center;gap:10px;font-size:13px;margin-bottom:14px;cursor:pointer">
        <input type="checkbox" name="require_login" <?php echo $form['require_login'] ? 'checked' : ''; ?>>
        ต้องเข้าสู่ระบบก่อนตอบแบบฟอร์ม
      </label>
      <label style="display:flex;align-items:center;gap:10px;font-size:13px;margin-bottom:18px;cursor:pointer">
        <input type="checkbox" name="publish" <?php echo $form['status'] === 'published' ? 'checked' : ''; ?>>
        เผยแพร่ฟอร์มนี้ (ให้ผู้อื่นตอบได้)
      </label>
      <label style="display:flex;align-items:center;gap:10px;font-size:13px;margin-bottom:18px;cursor:pointer">
        <input type="checkbox" name="accepting_responses" <?php echo ($form['accepting_responses'] ?? 1) ? 'checked' : ''; ?>>
        เปิดรับคำตอบ (ยกเลิกเพื่อปิดรับคำตอบชั่วคราว)
      </label>
      <div class="dialog-actions">
        <a class="btn btn-secondary" href="dashboard.php">ปิด</a>
        <button class="btn btn-primary" type="submit">บันทึก</button>
      </div>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
