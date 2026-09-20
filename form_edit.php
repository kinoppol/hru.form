<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_require_login();

$formId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM forms WHERE id = ? AND user_id = ?');
$stmt->execute([$formId, site_user_id()]);
$form = $stmt->fetch();

if (!$form) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$notices = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $title = trim($_POST['title'] ?? '');
    $shuffle = isset($_POST['shuffle']) ? 1 : 0;
    $showScore = isset($_POST['show_score']) ? 1 : 0;

    $pfKeys = $_POST['pf_key'] ?? [];
    $pfLabels = $_POST['pf_label'] ?? [];
    $pfTypes = $_POST['pf_type'] ?? [];
    $pfRequired = $_POST['pf_required'] ?? [];
    $personalFields = [];
    foreach ($pfKeys as $i => $key) {
        $label = trim($pfLabels[$i] ?? '');
        if ($label === '') continue;
        $personalFields[] = [
            'key' => $key !== '' ? $key : ('field_' . $i),
            'label' => $label,
            'type' => in_array($pfTypes[$i] ?? 'text', ['text', 'email', 'number'], true) ? $pfTypes[$i] : 'text',
            'required' => isset($pfRequired[$i]),
        ];
    }

    if ($title === '') {
        $errors[] = 'กรุณากรอกชื่อฟอร์ม';
    } else {
        $upd = db()->prepare('UPDATE forms SET title = ?, shuffle = ?, show_score = ?, personal_fields_json = ? WHERE id = ? AND user_id = ?');
        $upd->execute([$title, $shuffle, $showScore, json_encode($personalFields, JSON_UNESCAPED_UNICODE), $formId, site_user_id()]);
        $notices[] = 'บันทึกการตั้งค่าแล้ว';
        $stmt->execute([$formId, site_user_id()]);
        $form = $stmt->fetch();
    }
}

$qStmt = db()->prepare('SELECT * FROM questions WHERE form_id = ? ORDER BY sort_order, id');
$qStmt->execute([$formId]);
$questions = $qStmt->fetchAll();

$optStmt = db()->prepare('SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order, id');
$personalFields = json_decode($form['personal_fields_json'] ?? '[]', true) ?: [];

$assetPrefix = '';
$screenLabel = 'แก้ไขฟอร์ม';
$pageTitle = $form['title'];
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px">
    <h2 style="margin:0"><?php echo h($form['title']); ?></h2>
    <a class="btn btn-secondary" href="dashboard.php">← กลับแดชบอร์ด</a>
  </div>

  <?php foreach ($notices as $n): ?><div class="notice"><?php echo h($n); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="errors"><?php echo h($e); ?></div><?php endforeach; ?>

  <div class="card elev-sm" style="margin-bottom:20px">
    <div class="card-title">การตั้งค่าฟอร์ม</div>
    <form method="post">
      <input type="hidden" name="action" value="save_settings">
      <div class="field" style="margin-bottom:14px">
        <label for="title">ชื่อฟอร์ม</label>
        <input class="input" id="title" name="title" value="<?php echo h($form['title']); ?>" required>
      </div>
      <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="shuffle" <?php echo $form['shuffle'] ? 'checked' : ''; ?>> สุ่มลำดับคำถาม
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="show_score" <?php echo $form['show_score'] ? 'checked' : ''; ?>> แสดงคะแนนให้ผู้ตอบ
        </label>
      </div>

      <div style="font-size:13px;font-weight:600;margin-bottom:8px">ฟิลด์ข้อมูลผู้ตอบ (ส่วนที่ 1)</div>
      <div style="display:grid;gap:10px;margin-bottom:16px">
        <?php foreach ($personalFields as $i => $pf): ?>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="pf_key[]" value="<?php echo h($pf['key']); ?>">
            <input class="input" style="flex:2;min-width:160px" name="pf_label[]" value="<?php echo h($pf['label']); ?>" placeholder="ชื่อฟิลด์">
            <select class="input" style="flex:1;min-width:100px" name="pf_type[]">
              <?php foreach (['text' => 'ข้อความ', 'email' => 'อีเมล', 'number' => 'ตัวเลข'] as $val => $lbl): ?>
                <option value="<?php echo $val; ?>" <?php echo $pf['type'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
              <?php endforeach; ?>
            </select>
            <label style="display:flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" name="pf_required[<?php echo $i; ?>]" <?php echo !empty($pf['required']) ? 'checked' : ''; ?>> จำเป็น</label>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="pf_key[]" value="">
          <input class="input" style="flex:2;min-width:160px" name="pf_label[]" placeholder="เพิ่มฟิลด์ใหม่ เช่น เบอร์โทร">
          <select class="input" style="flex:1;min-width:100px" name="pf_type[]">
            <option value="text">ข้อความ</option>
            <option value="email">อีเมล</option>
            <option value="number">ตัวเลข</option>
          </select>
          <label style="display:flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" name="pf_required[<?php echo count($personalFields); ?>]"> จำเป็น</label>
        </div>
      </div>

      <button class="btn btn-primary" type="submit">บันทึกการตั้งค่า</button>
    </form>
  </div>

  <div class="card elev-sm">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div class="card-title">คำถาม (ส่วนที่ 2)</div>
      <a class="btn btn-secondary" href="question_form.php?form_id=<?php echo $formId; ?>">+ เพิ่มคำถาม</a>
    </div>

    <div style="display:grid;gap:10px;margin-top:10px">
      <?php foreach ($questions as $i => $q): ?>
        <?php
          $optStmt->execute([$q['id']]);
          $opts = $optStmt->fetchAll();
        ?>
        <div class="card elev-sm">
          <div class="card-kicker">คำถามที่ <?php echo $i + 1; ?> · <?php echo h(question_type_label($q['type'])); ?></div>
          <div class="card-title" style="font-size:15px"><?php echo h($q['text']); ?></div>
          <?php if (!empty($opts)): ?>
            <ul style="margin:6px 0 0;padding-left:18px;font-size:13px">
              <?php foreach ($opts as $o): ?>
                <li><?php echo h($o['label']); ?><?php echo $o['is_correct'] ? ' ✓' : ''; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <div style="display:flex;gap:8px;margin-top:8px">
            <a class="btn btn-secondary" href="question_form.php?form_id=<?php echo $formId; ?>&question_id=<?php echo $q['id']; ?>">แก้ไข</a>
            <form method="post" action="question_delete.php" onsubmit="return confirm('ลบคำถามนี้?')" style="margin:0">
              <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
              <input type="hidden" name="form_id" value="<?php echo $formId; ?>">
              <button class="btn btn-danger" type="submit">ลบ</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($questions)): ?>
        <p class="text-muted">ยังไม่มีคำถาม เพิ่มคำถามด้วยตนเอง หรือ<a href="import.php?form_id=<?php echo $formId; ?>">นำเข้าจากไฟล์</a></p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
