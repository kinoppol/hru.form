<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/import.php';
site_require_login();

$formId = (int) ($_GET['form_id'] ?? $_POST['form_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');

    if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'กรุณาเลือกไฟล์ที่ต้องการนำเข้า';
    } else {
        $filename = $_FILES['import_file']['name'];
        $allowedExt = ['txt', 'gift', 'csv'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'รองรับเฉพาะไฟล์ .txt (Aiken/GIFT) หรือ .csv เท่านั้น';
        } else {
            $content = file_get_contents($_FILES['import_file']['tmp_name']);
            $parsedQuestions = import_detect_and_parse($content, $filename);

            if (empty($parsedQuestions)) {
                $errors[] = 'ไม่พบคำถามที่นำเข้าได้จากไฟล์นี้ กรุณาตรวจสอบรูปแบบไฟล์';
            } else {
                if ($formId > 0) {
                    $chk = db()->prepare('SELECT id FROM forms WHERE id = ? AND user_id = ?');
                    $chk->execute([$formId, site_user_id()]);
                    if (!$chk->fetch()) {
                        $formId = 0;
                    }
                }

                if ($formId === 0) {
                    $ins = db()->prepare('INSERT INTO forms (user_id, title, status, share_token, personal_fields_json) VALUES (?, ?, ?, ?, ?)');
                    $ins->execute([
                        site_user_id(),
                        $title !== '' ? $title : pathinfo($filename, PATHINFO_FILENAME),
                        'draft',
                        generate_share_token(),
                        json_encode(default_personal_fields(), JSON_UNESCAPED_UNICODE),
                    ]);
                    $formId = (int) db()->lastInsertId();
                }

                $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), -1) FROM questions WHERE form_id = ' . $formId)->fetchColumn();
                $qIns = db()->prepare('INSERT INTO questions (form_id, type, text, scored, sort_order) VALUES (?, ?, ?, ?, ?)');
                $oIns = db()->prepare('INSERT INTO question_options (question_id, label, is_correct, sort_order) VALUES (?, ?, ?, ?)');

                foreach ($parsedQuestions as $q) {
                    $maxOrder++;
                    $qIns->execute([$formId, $q['type'], $q['text'], $q['scored'] ? 1 : 0, $maxOrder]);
                    $qid = (int) db()->lastInsertId();
                    foreach ($q['options'] as $j => $opt) {
                        $oIns->execute([$qid, $opt['label'], $opt['is_correct'] ? 1 : 0, $j]);
                    }
                }

                header('Location: form_edit.php?id=' . $formId . '&imported=' . count($parsedQuestions));
                exit;
            }
        }
    }
}

$assetPrefix = '';
$screenLabel = 'นำเข้าแบบทดสอบ';
$pageTitle = 'นำเข้าแบบทดสอบจากไฟล์';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap-narrow">
  <div class="card elev-md" style="padding:24px">
    <div class="dialog-title">นำเข้าแบบทดสอบจากไฟล์</div>
    <p class="text-muted">อัปโหลดไฟล์รูปแบบ Aiken, GIFT (.txt) หรือ CSV ระบบจะแยกคำถาม ตัวเลือก และเฉลยให้อัตโนมัติ</p>

    <?php foreach ($errors as $e): ?><div class="errors"><?php echo h($e); ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
      <?php if ($formId > 0): ?><input type="hidden" name="form_id" value="<?php echo $formId; ?>"><?php endif; ?>
      <?php if ($formId === 0): ?>
        <div class="field" style="margin-bottom:14px">
          <label for="title">ชื่อฟอร์มใหม่ (ถ้าไม่ระบุจะใช้ชื่อไฟล์)</label>
          <input class="input" id="title" name="title" placeholder="เช่น แบบทดสอบความรู้พื้นฐาน AI">
        </div>
      <?php endif; ?>
      <div class="field" style="margin-bottom:14px">
        <label for="import_file">เลือกไฟล์</label>
        <input type="file" id="import_file" name="import_file" accept=".txt,.gift,.csv" required>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px">
        <span class="tag tag-outline">Aiken (.txt)</span>
        <span class="tag tag-outline">GIFT (.txt / .gift)</span>
        <span class="tag tag-outline">CSV</span>
      </div>
      <div class="dialog-actions">
        <a class="btn btn-secondary" href="dashboard.php">ปิด</a>
        <button class="btn btn-primary" type="submit">นำเข้า</button>
      </div>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
