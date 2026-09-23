<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout_start.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

function save_setting(string $key, string $value): void
{
    if ($value === '') {
        db()->prepare('DELETE FROM settings WHERE `key` = ?')->execute([$key]);
    } else {
        db()->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$key, $value]);
    }
}

$notice = '';
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? 'general';
    if ($section === 'ai') {
        $provider = (string) ($_POST['ai_provider'] ?? 'openrouter');
        if (!array_key_exists($provider, ai_providers())) { $provider = 'openrouter'; }
        save_setting('ai_enabled', isset($_POST['ai_enabled']) ? '1' : '0');
        save_setting('ai_provider', $provider);
        save_setting('ai_base_url', trim((string) ($_POST['ai_base_url'] ?? '')));
        save_setting('ai_model', trim((string) ($_POST['ai_model'] ?? '')));
        $key = trim((string) ($_POST['ai_api_key'] ?? ''));
        if (isset($_POST['ai_clear_key'])) {
            save_setting('ai_api_key', '');
        } elseif ($key !== '') {
            save_setting('ai_api_key', $key); // blank keeps the stored key
        }
        if (isset($_POST['ai_test'])) {
            [$ok, $msg] = ai_chat([['role' => 'user', 'content' => 'ตอบกลับสั้น ๆ ว่า "พร้อมใช้งาน"']]);
            $testResult = [$ok, $ok ? 'เชื่อมต่อสำเร็จ: ' . mb_substr($msg, 0, 200) : $msg];
        } else {
            header('Location: settings.php?saved=1');
            exit;
        }
    } else {
        $name = trim((string) ($_POST['app_name'] ?? ''));
        if (mb_strlen($name) > 50) { $name = mb_substr($name, 0, 50); }
        save_setting('app_name', $name);
        header('Location: settings.php?saved=1');
        exit;
    }
}
if (isset($_GET['saved'])) { $notice = 'บันทึกแล้ว'; }
$current = db()->query("SELECT `value` FROM settings WHERE `key` = 'app_name'")->fetchColumn();
$ai = ai_settings();
?>
<div class="card">
  <h2>ตั้งค่าระบบ</h2>
  <?php if ($notice !== ''): ?><p><strong><?php echo h($notice); ?></strong></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="section" value="general">
    <div class="field">
      <label for="app_name">ชื่อระบบ</label>
      <input id="app_name" name="app_name" maxlength="50" placeholder="Noema" value="<?php echo h(is_string($current) ? $current : ''); ?>">
    </div>
    <p style="font-size:13px;opacity:.6">เว้นว่างเพื่อใช้ค่าเริ่มต้น (Noema)</p>
    <button class="btn" type="submit">บันทึก</button>
  </form>
</div>

<div class="card" style="margin-top:20px">
  <h2>ผู้ช่วย AI</h2>
  <p style="font-size:13px;opacity:.7">ไอคอนผู้ช่วยลอยมุมขวาล่างสำหรับผู้สร้างฟอร์ม ช่วยร่างแบบสอบถาม/แบบทดสอบ รองรับ API แบบ OpenAI-compatible</p>
  <?php if ($testResult !== null): ?>
    <p style="padding:10px;border-radius:8px;background:<?php echo $testResult[0] ? '#e7f7ec' : '#fdecec'; ?>;color:<?php echo $testResult[0] ? '#15803d' : '#b91c1c'; ?>"><?php echo h($testResult[1]); ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="section" value="ai">
    <div class="field">
      <label><input type="checkbox" name="ai_enabled" value="1" <?php echo $ai['ai_enabled'] === '1' ? 'checked' : ''; ?>> เปิดใช้งานผู้ช่วย AI</label>
    </div>
    <div class="field">
      <label for="ai_provider">ผู้ให้บริการ</label>
      <select id="ai_provider" name="ai_provider">
        <?php foreach (ai_providers() as $val => $p): ?>
          <option value="<?php echo h($val); ?>" <?php echo $ai['ai_provider'] === $val ? 'selected' : ''; ?>><?php echo h($p['label']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" id="ai_base_url_field">
      <label for="ai_base_url">Base URL (สำหรับ LLM server อื่น ๆ)</label>
      <input id="ai_base_url" name="ai_base_url" placeholder="เช่น http://localhost:11434/v1" value="<?php echo h($ai['ai_base_url']); ?>">
      <p style="font-size:12px;opacity:.6">ระบบจะเรียก {Base URL}/chat/completions</p>
    </div>
    <div class="field">
      <label for="ai_api_key">API Key</label>
      <input id="ai_api_key" name="ai_api_key" type="password" autocomplete="new-password" placeholder="<?php echo $ai['ai_api_key'] !== '' ? 'บันทึกไว้แล้ว (••••' . h(substr($ai['ai_api_key'], -4)) . ') — เว้นว่างเพื่อคงค่าเดิม' : 'วาง API key'; ?>">
      <?php if ($ai['ai_api_key'] !== ''): ?><label style="font-size:13px"><input type="checkbox" name="ai_clear_key" value="1"> ลบ API key ที่บันทึกไว้</label><?php endif; ?>
    </div>
    <div class="field">
      <label for="ai_model">Model</label>
      <input id="ai_model" name="ai_model" placeholder="เช่น google/gemini-2.5-flash, gemini-2.5-flash, llama3.1" value="<?php echo h($ai['ai_model']); ?>">
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn" type="submit">บันทึก</button>
      <button class="btn" type="submit" name="ai_test" value="1" style="opacity:.85">บันทึกและทดสอบการเชื่อมต่อ</button>
    </div>
  </form>
</div>
<script>
(function () {
  var sel = document.getElementById('ai_provider'), f = document.getElementById('ai_base_url_field');
  function sync() { f.style.display = sel.value === 'custom' ? '' : 'none'; }
  sel.addEventListener('change', sync); sync();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
