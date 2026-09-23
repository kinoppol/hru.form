<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_require_login();

$userId = site_user_id();
$showTrash = isset($_GET['trash']);

$trashStmt = db()->prepare('SELECT COUNT(*) FROM forms WHERE user_id = ? AND deleted_at IS NOT NULL');
$trashStmt->execute([$userId]);
$trashCount = (int) $trashStmt->fetchColumn();

$stmt = db()->prepare('
    SELECT f.*, (SELECT COUNT(*) FROM form_responses r WHERE r.form_id = f.id) AS response_count
    FROM forms f WHERE f.user_id = ? AND f.deleted_at IS ' . ($showTrash ? 'NOT NULL ORDER BY f.deleted_at DESC' : 'NULL ORDER BY f.created_at DESC') . '
');
$stmt->execute([$userId]);
$forms = $stmt->fetchAll();

$assetPrefix = '';
$screenLabel = 'Dashboard';
$pageTitle = 'ฟอร์มของฉัน';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap">
  <div class="page-head">
    <div>
      <h2 style="margin:0 0 4px"><?php echo $showTrash ? 'ถังขยะ' : 'ฟอร์มของฉัน'; ?></h2>
      <p class="page-sub"><?php echo $showTrash ? 'ฟอร์มที่ลบแล้ว กู้คืนได้ทุกเมื่อ คำถามและคำตอบยังอยู่ครบ' : 'จัดการฟอร์มและแบบทดสอบทั้งหมดของคุณที่นี่'; ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php if ($showTrash): ?>
      <a class="btn btn-secondary" href="dashboard.php">← กลับไปฟอร์มของฉัน</a>
      <?php else: ?>
      <a class="btn btn-secondary" href="dashboard.php?trash=1">🗑 ถังขยะ<?php if ($trashCount > 0): ?> (<?php echo $trashCount; ?>)<?php endif; ?></a>
      <a class="btn btn-secondary" href="import.php">นำเข้าแบบทดสอบ</a>
      <form method="post" action="form_new.php" style="margin:0"><button class="btn btn-primary" type="submit">+ สร้างฟอร์มใหม่</button></form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_GET['deleted'])): ?><div class="notice">ย้ายฟอร์มไปถังขยะแล้ว — <a href="dashboard.php?trash=1">ดูถังขยะเพื่อกู้คืน</a></div><?php endif; ?>
  <?php if (isset($_GET['restored'])): ?><div class="notice">กู้คืนฟอร์มแล้ว</div><?php endif; ?>

  <div class="grid-forms">
    <?php foreach ($forms as $form): ?>
      <?php $colors = theme_colors(); ?>
      <?php if ($showTrash): ?>
      <div class="card elev-sm">
        <div class="card-kicker">ลบเมื่อ <?php echo h(date('d M Y H:i', strtotime($form['deleted_at']))); ?></div>
        <div class="card-title"><?php echo h($form['title']); ?></div>
        <div class="card-meta"><span><?php echo (int) $form['response_count']; ?> คำตอบ · สร้างเมื่อ <?php echo h(date('d M Y', strtotime($form['created_at']))); ?></span></div>
        <form method="post" action="form_delete.php" style="margin:6px 0 0">
          <input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
          <input type="hidden" name="action" value="restore">
          <button class="btn btn-primary btn-block" type="submit">↺ กู้คืน</button>
        </form>
      </div>
      <?php continue; endif; ?>
      <div class="card elev-sm">
        <div class="card-kicker"><?php echo $form['status'] === 'published' ? 'เผยแพร่แล้ว' : 'ฉบับร่าง'; ?></div>
        <div class="card-title"><?php echo h($form['title']); ?></div>
        <div class="card-meta"><span><?php echo (int) $form['response_count']; ?> คำตอบ · สร้างเมื่อ <?php echo h(date('d M Y', strtotime($form['created_at']))); ?></span></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php if ($form['status'] === 'published'): ?><span class="tag <?php echo ($form['accepting_responses'] ?? 1) ? 'tag-accent' : 'tag-outline'; ?>"><?php echo ($form['accepting_responses'] ?? 1) ? 'เปิดรับคำตอบ' : 'ปิดรับคำตอบ'; ?></span><?php endif; ?>
          <?php if ($form['shuffle']): ?><span class="tag tag-neutral">สุ่มคำถาม</span><?php endif; ?>
          <span class="tag tag-neutral"><?php echo $form['show_score'] ? 'แสดงคะแนนให้ผู้ตอบ' : 'ไม่แสดงคะแนน'; ?></span>
        </div>
        <div>
          <div style="font-size:11px;opacity:.6;margin-bottom:5px">โทนสีของฟอร์ม</div>
          <form method="post" action="set_theme.php" style="display:flex;gap:6px">
            <input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
            <input type="hidden" name="return" value="dashboard">
            <?php foreach ($colors as $c): ?>
              <button type="submit" name="hex" value="<?php echo h($c['hex']); ?>" class="swatch<?php echo $c['hex'] === $form['theme_color'] ? ' active' : ''; ?>" style="background:<?php echo h($c['hex']); ?>" title="<?php echo h($c['label']); ?>" aria-label="<?php echo h($c['label']); ?>"></button>
            <?php endforeach; ?>
          </form>
        </div>
        <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
          <a class="btn btn-secondary" style="flex:1;text-align:center" href="form_edit.php?id=<?php echo (int) $form['id']; ?>">แก้ไข</a>
          <a class="btn btn-secondary" style="flex:1;text-align:center" href="form_responses.php?form_id=<?php echo (int) $form['id']; ?>">ดูคำตอบ <?php if ($form['response_count'] > 0): ?><span style="background:var(--color-accent);color:#fff;border-radius:99px;font-size:11px;padding:1px 7px;margin-left:4px"><?php echo (int)$form['response_count']; ?></span><?php endif; ?></a>
          <a class="btn btn-ghost btn-icon" aria-label="ดูตัวอย่าง" href="f.php?token=<?php echo h($form['share_token']); ?>" target="_blank">👁</a>
          <a class="btn btn-ghost btn-icon" aria-label="แชร์ลิงก์" href="share.php?id=<?php echo (int) $form['id']; ?>">🔗</a>
          <form method="post" action="form_delete.php" style="margin:0" onsubmit="return confirm('ย้ายฟอร์ม &quot;<?php echo h(addslashes($form['title'])); ?>&quot; ไปถังขยะ?\nผู้ตอบจะเข้าฟอร์มนี้ไม่ได้ แต่กู้คืนได้ภายหลัง')">
            <input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
            <button class="btn btn-ghost btn-icon" type="submit" aria-label="ลบฟอร์ม" title="ย้ายไปถังขยะ">🗑</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($forms)): ?>
      <p class="text-muted"><?php echo $showTrash ? 'ถังขยะว่างเปล่า' : 'ยังไม่มีฟอร์ม เริ่มต้นด้วยการสร้างฟอร์มใหม่ หรือ นำเข้าแบบทดสอบจากไฟล์'; ?></p>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
