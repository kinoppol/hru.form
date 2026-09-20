<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_require_login();

$formId = (int) ($_GET['form_id'] ?? $_POST['form_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM forms WHERE id = ? AND user_id = ?');
$stmt->execute([$formId, site_user_id()]);
$form = $stmt->fetch();
if (!$form) { header('Location: dashboard.php'); exit; }

$questionId = (int) ($_GET['question_id'] ?? $_POST['question_id'] ?? 0);
$question = null;
$options = [];

if ($questionId > 0) {
    $qs = db()->prepare('SELECT * FROM questions WHERE id = ? AND form_id = ?');
    $qs->execute([$questionId, $formId]);
    $question = $qs->fetch();
    if (!$question) { header('Location: form_edit.php?id=' . $formId); exit; }

    $os = db()->prepare('SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order, id');
    $os->execute([$questionId]);
    $options = $os->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'mc';
    $text = trim($_POST['text'] ?? '');
    $optionLabels = $_POST['option_label'] ?? [];
    $optionCorrect = $_POST['option_correct'] ?? [];

    if ($text === '') {
        $errors[] = 'กรุณากรอกคำถาม';
    } else {
        $scored = in_array($type, ['mc', 'checkbox', 'dropdown'], true) ? 1 : 0;

        if ($question) {
            $upd = db()->prepare('UPDATE questions SET type = ?, text = ?, scored = ? WHERE id = ?');
            $upd->execute([$type, $text, $scored, $questionId]);
            $del = db()->prepare('DELETE FROM question_options WHERE question_id = ?');
            $del->execute([$questionId]);
            $targetId = $questionId;
        } else {
            $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), -1) FROM questions WHERE form_id = ' . $formId)->fetchColumn();
            $ins = db()->prepare('INSERT INTO questions (form_id, type, text, scored, sort_order) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$formId, $type, $text, $scored, $maxOrder + 1]);
            $targetId = (int) db()->lastInsertId();
        }

        if (in_array($type, ['mc', 'checkbox', 'dropdown'], true)) {
            $optIns = db()->prepare('INSERT INTO question_options (question_id, label, is_correct, sort_order) VALUES (?, ?, ?, ?)');
            $order = 0;
            foreach ($optionLabels as $i => $label) {
                $label = trim($label);
                if ($label === '') continue;
                $isCorrect = isset($optionCorrect[$i]) ? 1 : 0;
                $optIns->execute([$targetId, $label, $isCorrect, $order]);
                $order++;
            }
        }

        header('Location: form_edit.php?id=' . $formId);
        exit;
    }
}

$type = $question['type'] ?? ($_POST['type'] ?? 'mc');
$text = $question['text'] ?? ($_POST['text'] ?? '');

$optionRows = $options;
if (empty($optionRows)) {
    $optionRows = array_fill(0, 4, ['label' => '', 'is_correct' => 0]);
}

$assetPrefix = '';
$screenLabel = 'คำถาม';
$pageTitle = $question ? 'แก้ไขคำถาม' : 'เพิ่มคำถาม';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap-narrow">
  <h2><?php echo $question ? 'แก้ไขคำถาม' : 'เพิ่มคำถาม'; ?></h2>
  <?php foreach ($errors as $e): ?><div class="errors"><?php echo h($e); ?></div><?php endforeach; ?>

  <form method="post" class="card elev-sm">
    <input type="hidden" name="form_id" value="<?php echo $formId; ?>">
    <?php if ($question): ?><input type="hidden" name="question_id" value="<?php echo $questionId; ?>"><?php endif; ?>

    <div class="field" style="margin-bottom:14px">
      <label for="type">ประเภทคำถาม</label>
      <select class="input" id="type" name="type" onchange="document.querySelectorAll('.opts-block').forEach(el => el.style.display = ['mc','checkbox','dropdown'].includes(this.value) ? 'grid' : 'none')">
        <?php foreach (['mc' => 'เลือกตอบข้อเดียว', 'checkbox' => 'เลือกได้หลายข้อ', 'dropdown' => 'เลือกจากรายการ', 'scale' => 'ระดับความเห็น (ไม่ให้คะแนน)', 'short' => 'คำตอบปลายเปิด (ไม่ให้คะแนน)'] as $val => $lbl): ?>
          <option value="<?php echo $val; ?>" <?php echo $type === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="margin-bottom:14px">
      <label for="text">ข้อความคำถาม</label>
      <textarea class="input" id="text" name="text" rows="3" required><?php echo h($text); ?></textarea>
    </div>

    <div class="opts-block" style="display:<?php echo in_array($type, ['mc', 'checkbox', 'dropdown'], true) ? 'grid' : 'none'; ?>;gap:8px;margin-bottom:16px">
      <label style="font-size:13px;font-weight:600">ตัวเลือกคำตอบ (ติ๊กถูกเพื่อกำหนดว่าเป็นคำตอบที่ถูกต้อง)</label>
      <?php for ($i = 0; $i < max(6, count($optionRows)); $i++): ?>
        <?php $o = $optionRows[$i] ?? ['label' => '', 'is_correct' => 0]; ?>
        <div style="display:flex;gap:8px;align-items:center">
          <input class="input" name="option_label[]" value="<?php echo h($o['label']); ?>" placeholder="ตัวเลือกที่ <?php echo $i + 1; ?>">
          <label style="display:flex;align-items:center;gap:4px;font-size:12px;white-space:nowrap"><input type="checkbox" name="option_correct[<?php echo $i; ?>]" <?php echo !empty($o['is_correct']) ? 'checked' : ''; ?>> ถูกต้อง</label>
        </div>
      <?php endfor; ?>
    </div>

    <div style="display:flex;gap:10px">
      <a class="btn btn-secondary" href="form_edit.php?id=<?php echo $formId; ?>">ยกเลิก</a>
      <button class="btn btn-primary" type="submit">บันทึกคำถาม</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
