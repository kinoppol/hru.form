<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_start_session();

$rid = (int) ($_GET['rid'] ?? 0);
$stmt = db()->prepare('SELECT r.*, f.title, f.theme_color, f.show_score, f.show_answers, f.user_id AS owner_id, f.share_token FROM form_responses r JOIN forms f ON f.id = r.form_id WHERE r.id = ? AND f.deleted_at IS NULL');
$stmt->execute([$rid]);
$response = $stmt->fetch();

if (!$response) {
    http_response_code(404);
    die('ไม่พบผลลัพธ์นี้');
}

$isOwner = site_check() && site_user_id() === (int) $response['owner_id'];
$isSelf = ($response['user_id'] !== null && site_check() && site_user_id() === (int) $response['user_id'])
    || (isset($_SESSION['own_responses']) && in_array($rid, $_SESSION['own_responses'], true));

if (!$isOwner && !$isSelf) {
    // First-time view right after submitting: allow once and remember it in-session.
    $_SESSION['own_responses'][] = $rid;
    $isSelf = true;
}

$showScoreEnabled   = (bool) $response['show_score'];
$showAnswersEnabled = (bool) ($response['show_answers'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    if (isset($_POST['toggle_show_score'])) {
        $showScoreEnabled = !$showScoreEnabled;
        db()->prepare('UPDATE forms SET show_score = ? WHERE id = ?')->execute([$showScoreEnabled ? 1 : 0, (int) $response['form_id']]);
        header('Location: results.php?rid=' . $rid); exit;
    }
    if (isset($_POST['toggle_show_answers'])) {
        $showAnswersEnabled = !$showAnswersEnabled;
        db()->prepare('UPDATE forms SET show_answers = ? WHERE id = ?')->execute([$showAnswersEnabled ? 1 : 0, (int) $response['form_id']]);
        header('Location: results.php?rid=' . $rid); exit;
    }
}

$aStmt = db()->prepare('
    SELECT ra.*, q.text, q.type, q.scored FROM response_answers ra
    JOIN questions q ON q.id = ra.question_id WHERE ra.response_id = ? ORDER BY ra.id
');
$aStmt->execute([$rid]);
$answerRows = $aStmt->fetchAll();

$optStmt = db()->prepare('SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order, id');

function format_answer_label(array $q, $answerJson, array $options): string
{
    $ans = json_decode($answerJson ?? 'null', true);
    if ($ans === null || $ans === '' || (is_array($ans) && empty($ans))) {
        return 'ไม่ได้ตอบ';
    }
    if ($q['type'] === 'checkbox') {
        $labels = [];
        foreach ($options as $o) {
            if (in_array((int) $o['id'], array_map('intval', $ans), true)) {
                $labels[] = $o['label'];
            }
        }
        return $labels ? implode(', ', $labels) : 'ไม่ได้ตอบ';
    }
    if (in_array($q['type'], ['mc', 'dropdown'], true)) {
        foreach ($options as $o) {
            if ((int) $o['id'] === (int) $ans) {
                return $o['label'];
            }
        }
        return 'ไม่ได้ตอบ';
    }
    return (string) $ans;
}

$assetPrefix = '';
$screenLabel = 'ผลลัพธ์';
$pageTitle = 'ผลลัพธ์ - ' . $response['title'];
require __DIR__ . '/includes/site_layout_start.php';
$personal = json_decode($response['personal_data_json'] ?? '{}', true) ?: [];
?>
<style>
@media print {
  .nav, .no-print { display: none !important; }
  .wrap-narrow { padding-top: 8px !important; max-width: 100% !important; }
  body { background: #fff !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
}
</style>
<section class="wrap-narrow" style="--color-accent:<?php echo h($response['theme_color']); ?>">

  <?php if ($isOwner && !empty($personal)): ?>
  <div class="card elev-sm no-print" style="margin-bottom:16px;padding:14px 18px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;font-size:13px">
    <span style="font-weight:700;opacity:.5;font-size:11px;text-transform:uppercase;letter-spacing:.05em">ผู้ตอบ</span>
    <?php foreach ($personal as $k => $v): if ($v === '') continue; ?>
      <span><strong><?php echo h($k); ?>:</strong> <?php echo h($v); ?></span>
    <?php endforeach; ?>
    <span style="flex:1"></span>
    <a class="btn btn-secondary no-print" style="font-size:12px;padding:6px 12px" href="form_responses.php?form_id=<?php echo (int)$response['form_id']; ?>">← คำตอบทั้งหมด</a>
    <button class="btn btn-secondary no-print" style="font-size:12px;padding:6px 12px" onclick="window.print()">🖨 พิมพ์</button>
  </div>
  <?php elseif ($isOwner): ?>
  <div class="no-print" style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px">
    <a class="btn btn-secondary" style="font-size:12px;padding:6px 12px" href="form_responses.php?form_id=<?php echo (int)$response['form_id']; ?>">← คำตอบทั้งหมด</a>
    <button class="btn btn-secondary" style="font-size:12px;padding:6px 12px" onclick="window.print()">🖨 พิมพ์</button>
  </div>
  <?php endif; ?>

  <!-- print header -->
  <div style="display:none" class="print-only">
    <h2 style="margin:0 0 4px"><?php echo h($response['title']); ?></h2>
    <?php if (!empty($personal)): ?>
      <p style="font-size:13px;margin:0 0 16px">
        <?php foreach ($personal as $k => $v): if ($v === '') continue; ?><?php echo h($k); ?>: <?php echo h($v); ?>  <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </div>
  <style>.print-only{display:none}@media print{.print-only{display:block!important}}</style>

  <div class="card elev-md" style="text-align:center;padding:36px">
    <?php if ($showScoreEnabled): ?>
      <div class="card-kicker">คะแนนของคุณ</div>
      <h1 style="font-size:48px;margin:8px 0"><?php echo (int) $response['score'] . ' / ' . (int) $response['max_score']; ?></h1>
      <p class="text-muted" style="margin:0">ทำได้ <?php echo $response['max_score'] > 0 ? round($response['score'] / $response['max_score'] * 100) : 0; ?>% ของคำถามที่ให้คะแนน</p>
    <?php else: ?>
      <div class="card-kicker">ส่งคำตอบสำเร็จ</div>
      <h2 style="margin:10px 0 6px">ขอบคุณสำหรับการตอบแบบทดสอบ</h2>
      <p class="text-muted" style="margin:0">เจ้าของฟอร์มเลือกไม่แสดงคะแนนสำหรับแบบทดสอบนี้</p>
    <?php endif; ?>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin:26px 0 14px;gap:12px;flex-wrap:wrap">
    <h3 style="margin:0">รายละเอียดคำตอบ</h3>
    <?php if ($isOwner): ?>
      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <form method="post" style="margin:0">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;opacity:.75;cursor:pointer">
            <input type="checkbox" name="toggle_show_score" onchange="this.form.submit()" <?php echo $showScoreEnabled ? 'checked' : ''; ?>>
            แสดงคะแนน
          </label>
        </form>
        <form method="post" style="margin:0">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;opacity:.75;cursor:pointer">
            <input type="checkbox" name="toggle_show_answers" onchange="this.form.submit()" <?php echo $showAnswersEnabled ? 'checked' : ''; ?>>
            แสดงเฉลย
          </label>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($showScoreEnabled || $showAnswersEnabled): ?>
    <div style="display:grid;gap:10px">
      <?php foreach ($answerRows as $i => $row): ?>
        <?php
          if (!$row['scored'] && !$showAnswersEnabled) continue;
          $optStmt->execute([$row['question_id']]); $opts = $optStmt->fetchAll();
          $ok = (bool) $row['is_correct'];
          $correctLabels = array_map(static fn ($o) => $o['label'], array_filter($opts, static fn ($o) => (bool) $o['is_correct']));
        ?>
        <div class="card elev-sm">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
            <div>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.45;margin-bottom:4px">ข้อที่ <?php echo $i + 1; ?></div>
              <div class="card-title" style="font-size:14px"><?php echo nl2br(h($row['text'])); ?></div>
            </div>
            <?php if ($row['scored'] && $showScoreEnabled): ?>
              <span class="tag <?php echo $ok ? 'tag-accent' : 'tag-neutral'; ?>" style="white-space:nowrap"><?php echo $ok ? '✓ ถูกต้อง' : '✗ ไม่ถูกต้อง'; ?></span>
            <?php endif; ?>
          </div>
          <p class="card-body" style="margin-top:6px">
            <strong>คำตอบของคุณ:</strong> <?php echo h(format_answer_label($row, $row['answer_json'], $opts)); ?>
          </p>
          <?php if ($showAnswersEnabled && !empty($correctLabels)): ?>
            <p class="card-body" style="margin-top:2px;color:<?php echo $ok ? '#3b7a57' : '#b45309'; ?>">
              <strong>เฉลย:</strong> <?php echo h(implode(', ', $correctLabels)); ?>
            </p>
          <?php elseif ($showScoreEnabled && $row['scored'] && !$ok && !empty($correctLabels)): ?>
            <p class="card-body" style="margin-top:2px;color:#b45309">
              <strong>เฉลย:</strong> <?php echo h(implode(', ', $correctLabels)); ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="no-print" style="display:flex;gap:10px;margin-top:26px">
    <a class="btn btn-secondary" style="flex:1;text-align:center" href="<?php echo $isOwner ? 'form_responses.php?form_id=' . (int)$response['form_id'] : 'index.php'; ?>"><?php echo $isOwner ? '← คำตอบทั้งหมด' : 'กลับหน้าหลัก'; ?></a>
    <a class="btn btn-ghost" style="flex:1;text-align:center" href="f.php?token=<?php echo h($response['share_token']); ?>">ลองทำอีกครั้ง</a>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
