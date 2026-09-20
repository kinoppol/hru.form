<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_start_session();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$stmt = db()->prepare('SELECT * FROM forms WHERE share_token = ?');
$stmt->execute([$token]);
$form = $stmt->fetch();

if (!$form) {
    http_response_code(404);
    die('ไม่พบฟอร์มนี้');
}

$isOwner = site_check() && site_user_id() === (int) $form['user_id'];

if ($form['status'] !== 'published' && !$isOwner) {
    $assetPrefix = '';
    $screenLabel = 'ตอบแบบฟอร์ม';
    $pageTitle = $form['title'];
    require __DIR__ . '/includes/site_layout_start.php';
    echo '<section class="wrap-narrow"><div class="notice">ฟอร์มนี้ยังไม่เปิดให้ตอบในขณะนี้</div></section>';
    require __DIR__ . '/includes/site_layout_end.php';
    exit;
}

if ($form['require_login'] && !site_check()) {
    $_SESSION['login_redirect'] = 'f.php?token=' . urlencode($token);
    header('Location: login.php');
    exit;
}

$personalFields = json_decode($form['personal_fields_json'] ?? '[]', true) ?: [];

$qStmt = db()->prepare('SELECT * FROM questions WHERE form_id = ? ORDER BY sort_order, id');
$qStmt->execute([$form['id']]);
$questions = $qStmt->fetchAll();

$optStmt = db()->prepare('SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order, id');
foreach ($questions as &$q) {
    $optStmt->execute([$q['id']]);
    $q['options'] = $optStmt->fetchAll();
}
unset($q);

$sessKey = 'respond_' . $token;
if (!isset($_SESSION[$sessKey])) {
    $order = array_column($questions, 'id');
    if ($form['shuffle']) {
        shuffle($order);
    }
    $_SESSION[$sessKey] = ['step' => 0, 'personal' => [], 'answers' => [], 'order' => $order];
}
$state = &$_SESSION[$sessKey];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '';

    if ($step === 'personal') {
        $personal = [];
        foreach ($personalFields as $pf) {
            $val = trim($_POST['pf_' . $pf['key']] ?? '');
            if (!empty($pf['required']) && $val === '') {
                $errors[$pf['key']] = 'จำเป็นต้องกรอกข้อมูลนี้';
            }
            $personal[$pf['key']] = $val;
        }
        if (empty($errors)) {
            $state['personal'] = $personal;
            $state['step'] = 1;
        }
    } elseif ($step === 'quiz') {
        $answers = [];
        foreach ($questions as $q) {
            $field = 'ans_' . $q['id'];
            if ($q['type'] === 'checkbox') {
                $answers[$q['id']] = array_map('intval', $_POST[$field] ?? []);
            } elseif ($q['type'] === 'short') {
                $answers[$q['id']] = trim($_POST[$field] ?? '');
            } elseif ($q['type'] === 'scale') {
                $answers[$q['id']] = $_POST[$field] !== '' && isset($_POST[$field]) ? (int) $_POST[$field] : null;
            } else {
                $answers[$q['id']] = isset($_POST[$field]) ? (int) $_POST[$field] : null;
            }
        }
        $state['answers'] = $answers;

        $score = 0;
        $maxScore = 0;
        $pdo = db();
        $pdo->beginTransaction();
        $insResp = $pdo->prepare('INSERT INTO form_responses (form_id, user_id, personal_data_json, score, max_score) VALUES (?, ?, ?, NULL, NULL)');
        $insResp->execute([$form['id'], site_user_id(), json_encode($state['personal'], JSON_UNESCAPED_UNICODE)]);
        $responseId = (int) $pdo->lastInsertId();

        $insAns = $pdo->prepare('INSERT INTO response_answers (response_id, question_id, answer_json, is_correct) VALUES (?, ?, ?, ?)');
        foreach ($questions as $q) {
            $ans = $answers[$q['id']] ?? null;
            $isCorrect = null;
            if ($q['scored']) {
                $maxScore++;
                $correctIds = array_column(array_filter($q['options'], static fn ($o) => (bool) $o['is_correct']), 'id');
                if ($q['type'] === 'checkbox') {
                    $given = $ans ?? [];
                    sort($given);
                    $correctSorted = $correctIds;
                    sort($correctSorted);
                    $isCorrect = $given === $correctSorted && !empty($given);
                } else {
                    $isCorrect = $ans !== null && in_array($ans, $correctIds, true);
                }
                if ($isCorrect) {
                    $score++;
                }
            }
            $insAns->execute([$responseId, $q['id'], json_encode($ans, JSON_UNESCAPED_UNICODE), $isCorrect === null ? null : (int) $isCorrect]);
        }

        $upd = $pdo->prepare('UPDATE form_responses SET score = ?, max_score = ? WHERE id = ?');
        $upd->execute([$score, $maxScore, $responseId]);
        $pdo->commit();

        unset($_SESSION[$sessKey]);
        header('Location: results.php?rid=' . $responseId);
        exit;
    } elseif ($step === 'back') {
        $state['step'] = 0;
    }
}

$assetPrefix = '';
$screenLabel = 'ตอบแบบฟอร์ม';
$pageTitle = $form['title'];
require __DIR__ . '/includes/site_layout_start.php';

$progressPct = $state['step'] === 0 ? '50%' : '100%';
?>
<section class="wrap-narrow" style="--color-accent:<?php echo h($form['theme_color']); ?>">
  <div class="progress-track"><div class="progress-fill" style="width:<?php echo $progressPct; ?>"></div></div>

  <?php if ($state['step'] === 0): ?>
    <span class="tag tag-outline">ส่วนที่ 1 จาก 2 · ข้อมูลผู้ตอบ</span>
    <h2 style="margin-top:14px">ข้อมูลผู้ตอบ</h2>
    <p class="text-muted" style="font-size:13px">ข้อมูลในส่วนนี้จะถูกจัดเก็บแยกจากคำตอบแบบทดสอบของคุณ</p>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo h($token); ?>">
      <input type="hidden" name="step" value="personal">
      <div style="display:grid;gap:16px;margin-top:8px">
        <?php foreach ($personalFields as $pf): ?>
          <div class="field">
            <label for="pf_<?php echo h($pf['key']); ?>"><?php echo h($pf['label']) . (!empty($pf['required']) ? ' *' : ' (ไม่บังคับ)'); ?></label>
            <input class="input" id="pf_<?php echo h($pf['key']); ?>" name="pf_<?php echo h($pf['key']); ?>" type="<?php echo h($pf['type']); ?>" value="<?php echo h($state['personal'][$pf['key']] ?? ''); ?>">
            <?php if (!empty($errors[$pf['key']])): ?><div style="font-size:12px;color:var(--color-accent-300);margin-top:4px"><?php echo h($errors[$pf['key']]); ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:22px">ถัดไป: เริ่มทำแบบทดสอบ</button>
    </form>

  <?php else: ?>
    <span class="tag tag-outline">ส่วนที่ 2 จาก 2 · แบบทดสอบ</span>
    <h2 style="margin-top:14px"><?php echo h($form['title']); ?></h2>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo h($token); ?>">
      <input type="hidden" name="step" value="quiz">
      <div style="display:grid;gap:14px;margin-top:8px">
        <?php
          $orderedQuestions = [];
          $byId = [];
          foreach ($questions as $q) { $byId[$q['id']] = $q; }
          foreach ($state['order'] as $qid) { if (isset($byId[$qid])) $orderedQuestions[] = $byId[$qid]; }
        ?>
        <?php foreach ($orderedQuestions as $i => $q): ?>
          <div class="card elev-sm">
            <div class="card-kicker">คำถามที่ <?php echo $i + 1; ?> · <?php echo h(question_type_label($q['type'])); ?></div>
            <div class="card-title" style="font-size:16px;margin-bottom:4px"><?php echo nl2br(h($q['text'])); ?></div>

            <?php if ($q['type'] === 'mc'): ?>
              <div style="display:grid;gap:6px;margin-top:6px">
                <?php foreach ($q['options'] as $o): ?>
                  <label class="radio"><input type="radio" name="ans_<?php echo $q['id']; ?>" value="<?php echo $o['id']; ?>" <?php echo (int) ($state['answers'][$q['id']] ?? 0) === (int) $o['id'] ? 'checked' : ''; ?>><span><?php echo h($o['label']); ?></span></label>
                <?php endforeach; ?>
              </div>
            <?php elseif ($q['type'] === 'checkbox'): ?>
              <div style="display:grid;gap:6px;margin-top:6px">
                <?php $checkedIds = $state['answers'][$q['id']] ?? []; ?>
                <?php foreach ($q['options'] as $o): ?>
                  <label class="radio"><input type="checkbox" name="ans_<?php echo $q['id']; ?>[]" value="<?php echo $o['id']; ?>" <?php echo in_array((int) $o['id'], $checkedIds, true) ? 'checked' : ''; ?>><span><?php echo h($o['label']); ?></span></label>
                <?php endforeach; ?>
              </div>
            <?php elseif ($q['type'] === 'dropdown'): ?>
              <select class="input" style="margin-top:6px" name="ans_<?php echo $q['id']; ?>">
                <option value="">— เลือกคำตอบ —</option>
                <?php foreach ($q['options'] as $o): ?>
                  <option value="<?php echo $o['id']; ?>" <?php echo (int) ($state['answers'][$q['id']] ?? 0) === (int) $o['id'] ? 'selected' : ''; ?>><?php echo h($o['label']); ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($q['type'] === 'scale'): ?>
              <div style="display:flex;gap:8px;margin-top:8px">
                <?php for ($v = 1; $v <= 5; $v++): ?>
                  <label style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;border:1px solid var(--color-divider);cursor:pointer;<?php echo (int) ($state['answers'][$q['id']] ?? 0) === $v ? 'background:var(--color-accent);color:#fff' : ''; ?>">
                    <input type="radio" name="ans_<?php echo $q['id']; ?>" value="<?php echo $v; ?>" style="display:none" <?php echo (int) ($state['answers'][$q['id']] ?? 0) === $v ? 'checked' : ''; ?>><?php echo $v; ?>
                  </label>
                <?php endfor; ?>
              </div>
            <?php elseif ($q['type'] === 'short'): ?>
              <textarea class="input" rows="3" style="margin-top:6px" name="ans_<?php echo $q['id']; ?>" placeholder="พิมพ์คำตอบของคุณ"><?php echo h($state['answers'][$q['id']] ?? ''); ?></textarea>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" name="step" value="back" class="btn btn-ghost" formnovalidate>ย้อนกลับ</button>
        <button type="submit" class="btn btn-primary btn-block">ส่งคำตอบ</button>
      </div>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
