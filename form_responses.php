<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
site_require_login();

$formId = (int) ($_GET['form_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM forms WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
$stmt->execute([$formId, site_user_id()]);
$form = $stmt->fetch();
if (!$form) { header('Location: dashboard.php'); exit; }

$personalFields = json_decode($form['personal_fields_json'] ?? '[]', true) ?: [];

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_response') {
    $delId = (int) ($_POST['response_id'] ?? 0);
    // verify the response belongs to this form (which is already owner-checked)
    $chk = db()->prepare('SELECT id FROM form_responses WHERE id = ? AND form_id = ?');
    $chk->execute([$delId, $formId]);
    if ($chk->fetch()) {
        db()->prepare('DELETE FROM form_responses WHERE id = ?')->execute([$delId]);
        $notice = 'ลบรายการตอบกลับแล้ว';
    }
    header('Location: form_responses.php?form_id=' . $formId . '&deleted=1');
    exit;
}
if (isset($_GET['deleted'])) {
    $notice = 'ลบรายการตอบกลับแล้ว';
}

$rStmt = db()->prepare('
    SELECT r.id, r.score, r.max_score, r.submitted_at, r.personal_data_json
    FROM form_responses r WHERE r.form_id = ? ORDER BY r.submitted_at ASC, r.id ASC
');
$rStmt->execute([$formId]);
$responses = $rStmt->fetchAll();
foreach ($responses as $n => &$rr) { $rr['no'] = $n + 1; }
unset($rr);

// sorting (applies to screen and print)
$sortBy  = (($_GET['sort'] ?? 'time') === 'score' && $form['show_score']) ? 'score' : 'time';
$sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
usort($responses, function ($a, $b) use ($sortBy, $sortDir) {
    $c = strcmp((string) $a['submitted_at'], (string) $b['submitted_at']);
    if ($sortBy === 'score') {
        $pa = $a['max_score'] > 0 ? $a['score'] / $a['max_score'] : 0;
        $pb = $b['max_score'] > 0 ? $b['score'] / $b['max_score'] : 0;
        $c = ($pa <=> $pb) ?: $c;
    }
    if ($c === 0) { $c = $a['id'] <=> $b['id']; }
    return $sortDir === 'asc' ? $c : -$c;
});

$total    = count($responses);
$avgScore = 0;
if ($total > 0 && $responses[0]['max_score'] > 0) {
    $avgScore = round(array_sum(array_column($responses, 'score')) / $total, 1);
}

$assetPrefix = '';
$screenLabel  = 'การตอบกลับ';
$pageTitle    = 'คำตอบ - ' . $form['title'];
require __DIR__ . '/includes/site_layout_start.php';
?>
<style>
@media print {
  .nav, .no-print { display: none !important; }
  .wrap { padding-top: 8px !important; }
  body { background: #fff !important; }
  .resp-table th, .resp-table td { font-size: 11px !important; }
}
</style>
<section class="wrap">
  <?php if ($notice !== ''): ?><div class="notice"><?php echo h($notice); ?></div><?php endif; ?>
  <div class="page-head">
    <div>
      <div class="card-kicker" style="margin-bottom:4px"><?php echo h($form['title']); ?></div>
      <h2 style="margin:0">รายการคำตอบทั้งหมด</h2>
    </div>
    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-secondary" href="form_edit.php?id=<?php echo $formId; ?>">← แก้ไขฟอร์ม</a>
      <form method="get" style="margin:0;display:flex;gap:6px;align-items:center">
        <input type="hidden" name="form_id" value="<?php echo $formId; ?>">
        <select name="sort" onchange="this.form.submit()" style="padding:8px 10px;border-radius:8px;border:1px solid var(--color-divider)">
          <option value="time"<?php echo $sortBy === 'time' ? ' selected' : ''; ?>>เรียงตามเวลาที่ตอบ</option>
          <?php if ($form['show_score']): ?><option value="score"<?php echo $sortBy === 'score' ? ' selected' : ''; ?>>เรียงตามคะแนน</option><?php endif; ?>
        </select>
        <select name="dir" onchange="this.form.submit()" style="padding:8px 10px;border-radius:8px;border:1px solid var(--color-divider)">
          <option value="desc"<?php echo $sortDir === 'desc' ? ' selected' : ''; ?>>มาก → น้อย / ใหม่ → เก่า</option>
          <option value="asc"<?php echo $sortDir === 'asc' ? ' selected' : ''; ?>>น้อย → มาก / เก่า → ใหม่</option>
        </select>
      </form>
      <button class="btn btn-secondary" onclick="window.print()">🖨 พิมพ์รายการ</button>
    </div>
  </div>

  <!-- summary stats -->
  <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:24px">
    <div class="card elev-sm" style="flex:1;min-width:140px;text-align:center;padding:18px 12px">
      <div class="card-kicker">จำนวนผู้ตอบ</div>
      <div style="font-size:32px;font-weight:800;color:var(--color-accent);margin-top:4px"><?php echo $total; ?></div>
    </div>
    <?php if ($form['show_score'] && $total > 0 && ($responses[0]['max_score'] ?? 0) > 0): ?>
    <div class="card elev-sm" style="flex:1;min-width:140px;text-align:center;padding:18px 12px">
      <div class="card-kicker">คะแนนเฉลี่ย</div>
      <div style="font-size:32px;font-weight:800;color:var(--color-accent);margin-top:4px"><?php echo $avgScore; ?><span style="font-size:16px;opacity:.5"> / <?php echo (int) ($responses[0]['max_score'] ?? 0); ?></span></div>
    </div>
    <div class="card elev-sm" style="flex:1;min-width:140px;text-align:center;padding:18px 12px">
      <div class="card-kicker">ผ่านเกณฑ์ 60%</div>
      <?php
        $maxS = (int) ($responses[0]['max_score'] ?? 0);
        $pass = $maxS > 0 ? count(array_filter($responses, fn($r) => $r['score'] / $maxS >= 0.6)) : 0;
      ?>
      <div style="font-size:32px;font-weight:800;color:var(--color-accent);margin-top:4px"><?php echo $pass; ?><span style="font-size:16px;opacity:.5"> คน</span></div>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($responses)): ?>
    <div class="card elev-sm" style="text-align:center;padding:40px;color:var(--color-neutral-800);opacity:.6">
      ยังไม่มีผู้ตอบแบบฟอร์มนี้
    </div>
  <?php else: ?>
  <div class="card elev-sm" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
      <table class="resp-table" style="width:100%;border-collapse:collapse;font-size:14px">
        <thead>
          <tr style="background:var(--color-neutral-100);font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.7">
            <th style="padding:12px 16px;text-align:left;font-weight:700">#</th>
            <?php foreach ($personalFields as $pf): ?>
              <th style="padding:12px 16px;text-align:left;font-weight:700"><?php echo h($pf['label']); ?></th>
            <?php endforeach; ?>
            <?php if ($form['show_score']): ?>
              <th style="padding:12px 16px;text-align:center;font-weight:700">คะแนน</th>
              <th style="padding:12px 16px;text-align:center;font-weight:700">%</th>
            <?php endif; ?>
            <th style="padding:12px 16px;text-align:left;font-weight:700">วันที่ส่ง</th>
            <th class="no-print" style="padding:12px 16px;text-align:center;font-weight:700">ดู/พิมพ์</th>
            <th class="no-print" style="padding:12px 16px;text-align:center;font-weight:700"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($responses as $i => $r): ?>
            <?php $pd = json_decode($r['personal_data_json'] ?? '{}', true) ?: []; ?>
            <?php $pct = ($r['max_score'] > 0) ? round($r['score'] / $r['max_score'] * 100) : null; ?>
            <tr style="border-top:1px solid var(--color-divider)">
              <td style="padding:12px 16px;opacity:.5;font-size:12px"><?php echo (int) $r['no']; ?></td>
              <?php foreach ($personalFields as $pf): ?>
                <td style="padding:12px 16px"><?php echo h($pd[$pf['key']] ?? '—'); ?></td>
              <?php endforeach; ?>
              <?php if ($form['show_score']): ?>
                <td style="padding:12px 16px;text-align:center;font-weight:700">
                  <?php echo $r['max_score'] > 0 ? (int)$r['score'] . ' / ' . (int)$r['max_score'] : '—'; ?>
                </td>
                <td style="padding:12px 16px;text-align:center">
                  <?php if ($pct !== null): ?>
                    <span style="display:inline-block;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:700;background:<?php echo $pct >= 60 ? 'var(--color-accent-100)' : 'var(--color-neutral-100)'; ?>;color:<?php echo $pct >= 60 ? 'var(--color-accent-800)' : 'var(--color-neutral-800)'; ?>"><?php echo $pct; ?>%</span>
                  <?php else: ?>—<?php endif; ?>
                </td>
              <?php endif; ?>
              <td style="padding:12px 16px;font-size:12px;opacity:.65"><?php echo h(date('d M Y H:i', strtotime($r['submitted_at']))); ?></td>
              <td class="no-print" style="padding:12px 16px;text-align:center">
                <a class="btn btn-secondary" style="font-size:12px;padding:6px 12px" href="results.php?rid=<?php echo (int)$r['id']; ?>">ดู / พิมพ์</a>
              </td>
              <td class="no-print" style="padding:12px 16px;text-align:center">
                <form method="post" style="margin:0" onsubmit="return confirm('ลบรายการตอบกลับนี้? ไม่สามารถกู้คืนได้')">
                  <input type="hidden" name="action" value="delete_response">
                  <input type="hidden" name="response_id" value="<?php echo (int)$r['id']; ?>">
                  <input type="hidden" name="form_id" value="<?php echo $formId; ?>">
                  <button class="btn btn-danger" style="font-size:12px;padding:6px 12px" type="submit">ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
