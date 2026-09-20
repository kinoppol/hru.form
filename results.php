<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_start_session();

$rid = (int) ($_GET['rid'] ?? 0);
$stmt = db()->prepare('SELECT r.*, f.title, f.theme_color, f.show_score, f.user_id AS owner_id, f.share_token FROM form_responses r JOIN forms f ON f.id = r.form_id WHERE r.id = ?');
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

$showScoreEnabled = (bool) $response['show_score'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['toggle_show_score'])) {
    $showScoreEnabled = !$showScoreEnabled;
    db()->prepare('UPDATE forms SET show_score = ? WHERE id = ?')->execute([$showScoreEnabled ? 1 : 0, (int) $response['form_id']]);
    header('Location: results.php?rid=' . $rid);
    exit;
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
?>
<section class="wrap-narrow" style="--color-accent:<?php echo h($response['theme_color']); ?>">
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
      <form method="post" style="margin:0">
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;opacity:.75;cursor:pointer">
          มุมมองเจ้าของฟอร์ม: แสดงคะแนน
          <input type="checkbox" name="toggle_show_score" onchange="this.form.submit()" <?php echo $showScoreEnabled ? 'checked' : ''; ?>>
        </label>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($showScoreEnabled): ?>
    <div style="display:grid;gap:10px">
      <?php foreach ($answerRows as $row): ?>
        <?php if (!$row['scored']) continue; ?>
        <?php $optStmt->execute([$row['question_id']]); $opts = $optStmt->fetchAll(); ?>
        <?php $ok = (bool) $row['is_correct']; ?>
        <div class="card elev-sm">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
            <div class="card-title" style="font-size:14px"><?php echo h($row['text']); ?></div>
            <span class="tag <?php echo $ok ? 'tag-accent' : 'tag-neutral'; ?>"><?php echo $ok ? 'ถูกต้อง' : 'ไม่ถูกต้อง'; ?></span>
          </div>
          <p class="card-body" style="margin-top:6px">คำตอบของคุณ: <?php echo h(format_answer_label($row, $row['answer_json'], $opts)); ?></p>
          <?php if (!$ok): ?>
            <?php $correctLabels = array_map(static fn ($o) => $o['label'], array_filter($opts, static fn ($o) => (bool) $o['is_correct'])); ?>
            <p class="card-body" style="margin-top:-6px">เฉลย: <?php echo h(implode(', ', $correctLabels)); ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:26px">
    <a class="btn btn-secondary" style="flex:1;text-align:center" href="<?php echo $isOwner ? 'dashboard.php' : 'index.php'; ?>">กลับหน้าหลัก</a>
    <a class="btn btn-ghost" style="flex:1;text-align:center" href="f.php?token=<?php echo h($response['share_token']); ?>">ลองทำอีกครั้ง</a>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
