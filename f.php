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
$navBrand = $form['title'];
require __DIR__ . '/includes/site_layout_start.php';

$accent = $form['theme_color'] ?? '#9184d9';
$stepNum  = $state['step'] === 0 ? 1 : 2;
$progressPct = $state['step'] === 0 ? 50 : 100;

$orderedQuestions = [];
if ($state['step'] === 1) {
    $byId = [];
    foreach ($questions as $q) { $byId[$q['id']] = $q; }
    foreach ($state['order'] as $qid) { if (isset($byId[$qid])) $orderedQuestions[] = $byId[$qid]; }
}
?>
<style>
:root { --fa: <?php echo h($accent); ?>; }
.f-shell{max-width:680px;margin:0 auto;padding:0 16px 80px}

/* top progress strip */
.f-prog-wrap{height:4px;background:rgba(0,0,0,.08);position:relative;overflow:hidden}
.f-prog-fill{height:100%;background:var(--fa);transition:width .5s cubic-bezier(.4,0,.2,1);border-radius:0 4px 4px 0}

/* hero banner */
.f-hero{background:linear-gradient(135deg,var(--fa) 0%,color-mix(in srgb,var(--fa) 60%,#1a0050) 100%);color:#fff;padding:36px 24px 100px;text-align:center;margin-bottom:-72px}
.f-hero-step{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:99px;padding:4px 14px 4px 8px;font-size:12px;font-weight:600;letter-spacing:.03em;margin-bottom:14px}
.f-hero-step-dot{width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
.f-hero h1{font-size:clamp(20px,4vw,28px);font-weight:800;margin:0 0 8px;line-height:1.25}
.f-hero-sub{font-size:13px;opacity:.8;margin:0}

/* floating card */
.f-card{background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(30,20,60,.12);padding:28px 28px 24px;position:relative;z-index:1}

/* personal fields */
.f-field{display:flex;flex-direction:column;gap:6px;margin-bottom:4px}
.f-field label{font-size:13px;font-weight:700;color:#44424c}
.f-input{width:100%;padding:11px 14px;border:1.5px solid #e6e3ee;border-radius:10px;font-size:15px;font-family:inherit;background:#faf9fc;color:#211f2b;transition:border-color .15s,box-shadow .15s;outline:none}
.f-input:focus{border-color:var(--fa);box-shadow:0 0 0 3px color-mix(in srgb,var(--fa) 20%,transparent)}
.f-err{font-size:12px;color:#dc2626;margin-top:3px}

/* option cards */
.f-opts{display:grid;gap:8px;margin-top:10px}
.f-opt{display:flex;align-items:center;gap:12px;padding:13px 16px;border:1.5px solid #e6e3ee;border-radius:12px;cursor:pointer;transition:border-color .15s,background .15s,box-shadow .15s;font-size:14px;font-weight:500;user-select:none}
.f-opt input{display:none}
.f-opt-mark{width:20px;height:20px;min-width:20px;border-radius:50%;border:2px solid #ccc;display:flex;align-items:center;justify-content:center;transition:all .15s;font-size:10px}
.f-opt:has(input[type=checkbox]) .f-opt-mark{border-radius:5px}
.f-opt:hover{border-color:color-mix(in srgb,var(--fa) 50%,#e6e3ee);background:color-mix(in srgb,var(--fa) 5%,#fff)}
.f-opt:has(input:checked){border-color:var(--fa);background:color-mix(in srgb,var(--fa) 10%,#fff);box-shadow:0 0 0 3px color-mix(in srgb,var(--fa) 15%,transparent)}
.f-opt:has(input:checked) .f-opt-mark{background:var(--fa);border-color:var(--fa);color:#fff}

/* question card */
.f-q{background:#fff;border:1px solid #f0eef6;border-radius:16px;padding:20px 20px 16px;box-shadow:0 2px 12px rgba(30,20,60,.05)}
.f-q-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:color-mix(in srgb,var(--fa) 15%,#fff);color:var(--fa);font-size:12px;font-weight:800;margin-bottom:10px}
.f-q-text{font-size:16px;font-weight:700;line-height:1.5;margin-bottom:4px;color:#211f2b}
.f-q-type{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;opacity:.45;margin-bottom:0}

/* scale */
.f-scale{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.f-scale-opt{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:12px;border:1.5px solid #e6e3ee;cursor:pointer;font-size:15px;font-weight:700;transition:all .15s;user-select:none}
.f-scale-opt input{display:none}
.f-scale-opt:hover{border-color:var(--fa)}
.f-scale-opt:has(input:checked){background:var(--fa);border-color:var(--fa);color:#fff;box-shadow:0 4px 12px color-mix(in srgb,var(--fa) 40%,transparent)}

/* buttons */
.f-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 24px;border-radius:12px;font-size:15px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .18s}
.f-btn-primary{background:var(--fa);color:#fff;width:100%;box-shadow:0 4px 16px color-mix(in srgb,var(--fa) 35%,transparent)}
.f-btn-primary:hover{filter:brightness(1.08);box-shadow:0 6px 22px color-mix(in srgb,var(--fa) 45%,transparent);transform:translateY(-1px)}
.f-btn-ghost{background:transparent;color:#44424c;border:1.5px solid #e6e3ee}
.f-btn-ghost:hover{background:#f4f3f8}

/* ===== modern AI-era layer ===== */
body{background:radial-gradient(900px 500px at 10% 0%,color-mix(in srgb,var(--fa) 10%,transparent),transparent 70%),radial-gradient(800px 500px at 100% 40%,rgba(56,189,248,.08),transparent 70%),#f8f8fc}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.72)!important;backdrop-filter:saturate(180%) blur(16px);-webkit-backdrop-filter:saturate(180%) blur(16px);border-bottom:1px solid rgba(255,255,255,.6)!important;box-shadow:0 1px 0 rgba(30,20,60,.05)}
.f-prog-wrap{background:rgba(0,0,0,.05)}
.f-prog-fill{background:linear-gradient(90deg,var(--fa),#38bdf8,var(--fa));background-size:200% 100%;animation:f-flow 3s linear infinite;box-shadow:0 0 12px var(--fa)}
@keyframes f-flow{to{background-position:-200% 0}}

.f-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--fa) 0%,color-mix(in srgb,var(--fa) 55%,#0b1140) 100%)}
.f-hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.07) 1px,transparent 1px);background-size:36px 36px;-webkit-mask-image:radial-gradient(ellipse at 80% 0%,#000,transparent 70%);mask-image:radial-gradient(ellipse at 80% 0%,#000,transparent 70%)}
.f-hero::after{content:'';position:absolute;width:420px;height:420px;left:-120px;bottom:-220px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.22),transparent 65%);animation:f-drift 9s ease-in-out infinite alternate}
@keyframes f-drift{to{transform:translate(80px,-30px) scale(1.15)}}
.f-hero>*{position:relative;z-index:1}
.f-hero h1{text-shadow:0 2px 20px rgba(0,0,0,.15)}
.f-hero-step{backdrop-filter:blur(8px)}

/* AI orb — top right */
.ai-orb{position:absolute!important;top:18px;right:24px;width:84px;height:84px;z-index:2;pointer-events:none}
.ai-orb .core{position:absolute;inset:26px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff,#c7d2fe 40%,#818cf8 75%);box-shadow:0 0 24px 6px rgba(255,255,255,.55),0 0 50px 14px rgba(129,140,248,.6);animation:orb-pulse 2.6s ease-in-out infinite}
.ai-orb .ring{position:absolute;inset:6px;border-radius:50%;background:conic-gradient(from 0deg,transparent 0 55%,rgba(255,255,255,.95) 80%,transparent);-webkit-mask:radial-gradient(farthest-side,transparent calc(100% - 2px),#000 calc(100% - 1px));mask:radial-gradient(farthest-side,transparent calc(100% - 2px),#000 calc(100% - 1px));animation:orb-spin 3.2s linear infinite}
.ai-orb .ring.r2{inset:-4px;opacity:.55;animation-duration:5.5s;animation-direction:reverse}
.ai-orb .spark{position:absolute;left:50%;top:50%;width:6px;height:6px;margin:-3px;border-radius:50%;background:#fff;box-shadow:0 0 8px 2px rgba(255,255,255,.9);animation:orb-orbit 4s linear infinite}
.ai-orb .spark.s2{animation-duration:6.5s;animation-delay:-2s;width:4px;height:4px;margin:-2px;background:#a5f3fc}
@keyframes orb-spin{to{transform:rotate(360deg)}}
@keyframes orb-pulse{50%{transform:scale(1.18);filter:brightness(1.15)}}
@keyframes orb-orbit{from{transform:rotate(0) translateX(46px)}to{transform:rotate(360deg) translateX(46px)}}
@media(max-width:600px){.ai-orb{width:56px;height:56px;top:12px;right:12px}.ai-orb .core{inset:17px}.ai-orb .spark{display:none}}

.f-card,.f-q{backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.7);animation:f-rise .6s cubic-bezier(.2,.8,.2,1) both}
.f-card{background:rgba(255,255,255,.88);box-shadow:0 20px 60px rgba(30,20,60,.14),0 0 0 1px rgba(30,20,60,.04)}
.f-q{background:rgba(255,255,255,.92);transition:transform .2s,box-shadow .2s}
.f-q:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(30,20,60,.1)}
.f-q:nth-child(2){animation-delay:.05s}.f-q:nth-child(3){animation-delay:.1s}.f-q:nth-child(4){animation-delay:.15s}.f-q:nth-child(n+5){animation-delay:.2s}
@keyframes f-rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.f-input{background:rgba(250,249,252,.9);border-radius:12px}
.f-btn-primary{background:linear-gradient(135deg,var(--fa),color-mix(in srgb,var(--fa) 70%,#38bdf8));position:relative;overflow:hidden}
.f-btn-primary::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);animation:f-shine 3.5s ease-in-out infinite}
@keyframes f-shine{60%,100%{left:130%}}
.f-opt:has(input:checked){transform:scale(1.01)}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}

/* section heading in card */
.f-section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:color-mix(in srgb,var(--fa) 80%,#333);margin-bottom:18px;display:flex;align-items:center;gap:8px}
.f-section-label::after{content:'';flex:1;height:1px;background:color-mix(in srgb,var(--fa) 20%,#e6e3ee)}
</style>

<!-- progress bar sticks to top of <main> -->
<div class="f-prog-wrap">
  <div class="f-prog-fill" style="width:<?php echo $progressPct; ?>%"></div>
</div>

<!-- hero -->
<div class="f-hero">
  <div class="ai-orb" aria-hidden="true">
    <span class="ring r2"></span><span class="ring"></span><span class="core"></span>
    <span class="spark"></span><span class="spark s2"></span>
  </div>
  <div class="f-hero-step">
    <span class="f-hero-step-dot"><?php echo $stepNum; ?></span>
    <?php echo $state['step'] === 0 ? 'ส่วนที่ 1 / 2 · ข้อมูลผู้ตอบ' : 'ส่วนที่ 2 / 2 · แบบทดสอบ'; ?>
  </div>
  <h1><?php echo $state['step'] === 0 ? 'ข้อมูลผู้ตอบ' : h($form['title']); ?></h1>
  <?php if ($state['step'] === 0): ?>
    <p class="f-hero-sub">กรุณากรอกข้อมูลเพื่อระบุตัวตนก่อนเริ่มทำแบบทดสอบ</p>
  <?php else: ?>
    <p class="f-hero-sub"><?php echo count($orderedQuestions); ?> ข้อ · เลือกคำตอบที่ถูกต้องที่สุด</p>
  <?php endif; ?>
</div>

<div class="f-shell">
  <?php if ($state['step'] === 0): ?>
  <div class="f-card">
    <div class="f-section-label">ข้อมูลส่วนตัว</div>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo h($token); ?>">
      <input type="hidden" name="step" value="personal">
      <div style="display:grid;gap:18px">
        <?php foreach ($personalFields as $pf): ?>
          <div class="f-field">
            <label for="pf_<?php echo h($pf['key']); ?>">
              <?php echo h($pf['label']); ?>
              <?php if (!empty($pf['required'])): ?><span style="color:var(--fa);margin-left:3px">*</span><?php else: ?><span style="font-weight:400;opacity:.5;font-size:12px"> (ไม่บังคับ)</span><?php endif; ?>
            </label>
            <input class="f-input" id="pf_<?php echo h($pf['key']); ?>" name="pf_<?php echo h($pf['key']); ?>" type="<?php echo h($pf['type']); ?>" value="<?php echo h($state['personal'][$pf['key']] ?? ''); ?>" <?php echo !empty($pf['required']) ? 'required' : ''; ?>>
            <?php if (!empty($errors[$pf['key']])): ?><div class="f-err"><?php echo h($errors[$pf['key']]); ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="f-btn f-btn-primary" style="margin-top:28px">
        ถัดไป: เริ่มทำแบบทดสอบ →
      </button>
    </form>
  </div>

  <?php else: ?>
  <form method="post" id="quiz-form" data-require-all="<?php echo (int) ($form['require_all_answers'] ?? 1); ?>">
    <input type="hidden" name="token" value="<?php echo h($token); ?>">
    <input type="hidden" name="step" value="quiz">
    <div id="quiz-error-banner" style="display:none;background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:14px;color:#b91c1c;font-weight:600"></div>
    <div style="display:grid;gap:14px">
      <?php foreach ($orderedQuestions as $i => $q): ?>
        <div class="f-q" data-qid="<?php echo (int) $q['id']; ?>" data-qtype="<?php echo h($q['type']); ?>" data-qno="<?php echo $i + 1; ?>">
          <div class="f-q-num"><?php echo $i + 1; ?></div>
          <div class="f-q-text"><?php echo nl2br(h($q['text'])); ?></div>
          <div class="f-q-type"><?php echo h(question_type_label($q['type'])); ?></div>

          <?php if ($q['type'] === 'mc'): ?>
            <div class="f-opts">
              <?php foreach ($q['options'] as $oi => $o): ?>
                <label class="f-opt">
                  <input type="radio" name="ans_<?php echo $q['id']; ?>" value="<?php echo $o['id']; ?>" <?php echo (int) ($state['answers'][$q['id']] ?? 0) === (int) $o['id'] ? 'checked' : ''; ?>>
                  <span class="f-opt-mark"><?php echo chr(65 + $oi); ?></span>
                  <span><?php echo h($o['label']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($q['type'] === 'checkbox'): ?>
            <div class="f-opts">
              <?php $checkedIds = $state['answers'][$q['id']] ?? []; ?>
              <?php foreach ($q['options'] as $oi => $o): ?>
                <label class="f-opt">
                  <input type="checkbox" name="ans_<?php echo $q['id']; ?>[]" value="<?php echo $o['id']; ?>" <?php echo in_array((int) $o['id'], $checkedIds, true) ? 'checked' : ''; ?>>
                  <span class="f-opt-mark">✓</span>
                  <span><?php echo h($o['label']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($q['type'] === 'dropdown'): ?>
            <div style="margin-top:10px">
              <select class="f-input" name="ans_<?php echo $q['id']; ?>">
                <option value="">— เลือกคำตอบ —</option>
                <?php foreach ($q['options'] as $o): ?>
                  <option value="<?php echo $o['id']; ?>" <?php echo (int) ($state['answers'][$q['id']] ?? 0) === (int) $o['id'] ? 'selected' : ''; ?>><?php echo h($o['label']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

          <?php elseif ($q['type'] === 'scale'): ?>
            <div class="f-scale">
              <?php for ($v = 1; $v <= 5; $v++): ?>
                <label class="f-scale-opt" title="<?php echo $v; ?>">
                  <input type="radio" name="ans_<?php echo $q['id']; ?>" value="<?php echo $v; ?>" <?php echo (int) ($state['answers'][$q['id']] ?? 0) === $v ? 'checked' : ''; ?>>
                  <?php echo $v; ?>
                </label>
              <?php endfor; ?>
              <span style="font-size:12px;opacity:.5;align-self:center;margin-left:4px">น้อย → มาก</span>
            </div>

          <?php elseif ($q['type'] === 'short'): ?>
            <textarea class="f-input" rows="3" style="margin-top:10px;resize:vertical" name="ans_<?php echo $q['id']; ?>" placeholder="พิมพ์คำตอบของคุณที่นี่…"><?php echo h($state['answers'][$q['id']] ?? ''); ?></textarea>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:10px;margin-top:24px">
      <button type="submit" name="step" value="back" class="f-btn f-btn-ghost" formnovalidate style="min-width:110px">← ย้อนกลับ</button>
      <button type="submit" id="quiz-submit" class="f-btn f-btn-primary">ส่งคำตอบ ✓</button>
    </div>
  </form>
  <script>
  (function(){
    var form = document.getElementById('quiz-form');
    if (!form || form.dataset.requireAll !== '1') return;
    var submitBtn = document.getElementById('quiz-submit');
    var banner = document.getElementById('quiz-error-banner');
    submitBtn.addEventListener('click', function(e){
      var unanswered = [];
      form.querySelectorAll('.f-q[data-qid]').forEach(function(card){
        var qid = card.dataset.qid;
        var qtype = card.dataset.qtype;
        var qno = card.dataset.qno;
        var answered = false;
        if (qtype === 'checkbox') {
          answered = card.querySelectorAll('input[type=checkbox]:checked').length > 0;
        } else if (qtype === 'short') {
          var ta = card.querySelector('textarea');
          answered = ta && ta.value.trim() !== '';
        } else if (qtype === 'dropdown') {
          var sel = card.querySelector('select');
          answered = sel && sel.value !== '';
        } else {
          answered = card.querySelectorAll('input[type=radio]:checked').length > 0;
        }
        if (!answered) unanswered.push(parseInt(qno));
      });
      if (unanswered.length > 0) {
        e.preventDefault();
        banner.style.display = 'block';
        banner.textContent = 'กรุณาตอบข้อที่ยังไม่ได้ตอบ: ข้อ ' + unanswered.join(', ');
        banner.scrollIntoView({behavior:'smooth', block:'nearest'});
        var firstCard = form.querySelector('.f-q[data-qno="' + unanswered[0] + '"]');
        if (firstCard) firstCard.scrollIntoView({behavior:'smooth', block:'center'});
      }
    });
    form.addEventListener('change', function(){ banner.style.display = 'none'; });
    form.addEventListener('input', function(){ banner.style.display = 'none'; });
  })();
  </script>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
