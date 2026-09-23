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
$aiError = '';
$ai = ai_settings();
$aiModels = ai_enabled_models($ai);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['section'] ?? '') === 'ai') {
        $cfg = ai_config_from_input($_POST);
        $enabled = isset($_POST['ai_enabled']);
        $models = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['ai_models'] ?? [])), static fn ($m) => $m !== '')));
        $default = trim((string) ($_POST['ai_model'] ?? ''));
        if ($default === '' || !in_array($default, $models, true)) { $default = $models[0] ?? ''; }

        if ($enabled && $default === '') {
            $aiError = 'กรุณาเปิดใช้งานอย่างน้อย 1 โมเดลก่อนเปิดผู้ช่วย AI';
        } elseif ($default !== '') {
            // Always verify the connection with the default model before persisting anything.
            [$ok, $msg] = ai_chat([['role' => 'user', 'content' => 'Reply with exactly: OK']], $default, $cfg, false);
            if (!$ok) { $aiError = 'ทดสอบโมเดล ' . $default . ' ไม่ผ่าน จึงยังไม่บันทึก — ' . $msg; }
        }

        if ($aiError === '') {
            save_setting('ai_enabled', $enabled ? '1' : '0');
            save_setting('ai_provider', $cfg['ai_provider']);
            save_setting('ai_base_url', $cfg['ai_base_url']);
            save_setting('ai_api_key', $cfg['ai_api_key']);
            save_setting('ai_model', $default);
            save_setting('ai_models', $models ? json_encode($models, JSON_UNESCAPED_UNICODE) : '');
            header('Location: settings.php?saved=ai#ai');
            exit;
        }
        // Re-render with what the admin submitted.
        $ai = array_merge($ai, $cfg, ['ai_enabled' => $enabled ? '1' : '0', 'ai_model' => $default]);
        $aiModels = $models;
    } else {
        $name = trim((string) ($_POST['app_name'] ?? ''));
        if (mb_strlen($name) > 50) { $name = mb_substr($name, 0, 50); }
        save_setting('app_name', $name);
        header('Location: settings.php?saved=1');
        exit;
    }
}
if (isset($_GET['saved'])) { $notice = $_GET['saved'] === 'ai' ? 'ทดสอบผ่านและบันทึกการตั้งค่าผู้ช่วย AI แล้ว' : 'บันทึกแล้ว'; }
$current = db()->query("SELECT `value` FROM settings WHERE `key` = 'app_name'")->fetchColumn();
$hasKey = $ai['ai_api_key'] !== '';
?>
<?php if ($notice !== ''): ?><div class="notice"><?php echo h($notice); ?></div><?php endif; ?>
<div class="card">
  <h2>ตั้งค่าระบบ</h2>
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

<div class="card ai-card" id="ai">
  <div class="ai-card-head">
    <div class="ai-card-icon">✦</div>
    <div style="flex:1">
      <h2>ผู้ช่วย AI</h2>
      <p class="ai-sub">ไอคอนลอยช่วยผู้สร้างฟอร์มร่างแบบสอบถามและแบบทดสอบ เชื่อมต่อผ่าน API แบบ OpenAI-compatible</p>
    </div>
    <label class="ai-switch" title="เปิด/ปิดผู้ช่วย AI">
      <input type="checkbox" name="ai_enabled" value="1" form="ai-form" <?php echo $ai['ai_enabled'] === '1' ? 'checked' : ''; ?>>
      <span class="ai-slider"></span>
    </label>
  </div>

  <?php if ($aiError !== ''): ?><div class="errors"><?php echo h($aiError); ?></div><?php endif; ?>

  <form method="post" id="ai-form" action="settings.php#ai">
    <input type="hidden" name="section" value="ai">

    <div class="ai-step"><span class="ai-step-no">1</span> เลือกผู้ให้บริการ</div>
    <div class="ai-providers">
      <?php foreach (ai_providers() as $val => $p): ?>
        <label class="ai-provider">
          <input type="radio" name="ai_provider" value="<?php echo h($val); ?>" data-key-url="<?php echo h($p['key_url']); ?>" <?php echo $ai['ai_provider'] === $val ? 'checked' : ''; ?>>
          <span class="ai-provider-body">
            <strong><?php echo h($p['label']); ?></strong>
            <small><?php echo h($p['hint']); ?></small>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="ai-step"><span class="ai-step-no">2</span> ข้อมูลการเชื่อมต่อ</div>
    <div class="field" id="ai_base_url_field">
      <label for="ai_base_url">Base URL</label>
      <input id="ai_base_url" name="ai_base_url" placeholder="เช่น http://localhost:11434/v1" value="<?php echo h($ai['ai_base_url']); ?>">
      <p class="ai-hint">ระบบจะเรียก <code>{Base URL}/models</code> และ <code>{Base URL}/chat/completions</code></p>
    </div>
    <div class="field">
      <label for="ai_api_key">API Key <a class="ai-key-link" id="ai_key_link" href="#" target="_blank" rel="noopener">รับ API key ↗</a></label>
      <div class="ai-key-row">
        <input id="ai_api_key" name="ai_api_key" type="password" autocomplete="new-password"
               placeholder="<?php echo $hasKey ? 'บันทึกไว้แล้ว ••••' . h(substr($ai['ai_api_key'], -4)) . ' — เว้นว่างเพื่อใช้ค่าเดิม' : 'วาง API key ที่นี่'; ?>">
        <button type="button" class="ai-ghost" id="ai_key_toggle" title="แสดง/ซ่อน">👁</button>
      </div>
      <?php if ($hasKey): ?><label class="ai-inline"><input type="checkbox" name="ai_clear_key" value="1"> ลบ API key ที่บันทึกไว้</label><?php endif; ?>
    </div>
    <div class="ai-connect">
      <button type="button" class="btn" id="ai_connect">ทดสอบการเชื่อมต่อ &amp; โหลดโมเดล</button>
      <span class="ai-status" id="ai_status"><?php echo $aiModels ? 'ใช้งานอยู่ ' . count($aiModels) . ' โมเดล — กดทดสอบเพื่อโหลดรายการทั้งหมด' : 'ยังไม่ได้ทดสอบ'; ?></span>
    </div>

    <div class="ai-step"><span class="ai-step-no">3</span> เลือกโมเดลที่เปิดให้ใช้งาน</div>
    <div class="ai-models-box">
      <div class="ai-models-tools">
        <input type="search" id="ai_model_search" placeholder="ค้นหาโมเดล เช่น gemini, llama, free">
        <label class="ai-inline"><input type="checkbox" id="ai_only_on"> แสดงเฉพาะที่เปิดใช้</label>
        <span class="ai-count" id="ai_count"></span>
      </div>
      <div class="ai-models" id="ai_models">
        <div class="ai-empty" id="ai_models_empty">กด “ทดสอบการเชื่อมต่อ &amp; โหลดโมเดล” เพื่อดึงรายการโมเดล</div>
      </div>
      <p class="ai-hint">ติ๊กเพื่อเปิดให้ผู้ใช้เลือกใช้ ★ = โมเดลเริ่มต้น · กด “ทดสอบ” เพื่อลองเรียกโมเดลนั้นจริง</p>
    </div>

    <div class="ai-actions">
      <span class="ai-hint">เมื่อกดบันทึก ระบบจะทดสอบโมเดลเริ่มต้นอีกครั้ง หากไม่ผ่านจะไม่บันทึก</span>
      <button class="btn" type="submit" id="ai_save">ทดสอบ &amp; บันทึก</button>
    </div>
  </form>
</div>

<script>
(function () {
  var form = document.getElementById('ai-form');
  var enabledModels = <?php echo json_encode($aiModels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
  var defaultModel = <?php echo json_encode($ai['ai_model'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
  var all = enabledModels.map(function (id) { return { id: id, name: id }; });
  var box = document.getElementById('ai_models');
  var empty = document.getElementById('ai_models_empty');
  var search = document.getElementById('ai_model_search');
  var onlyOn = document.getElementById('ai_only_on');
  var count = document.getElementById('ai_count');
  var status = document.getElementById('ai_status');
  var connectBtn = document.getElementById('ai_connect');

  function providerInput() { return form.querySelector('input[name=ai_provider]:checked'); }
  function syncProvider() {
    var p = providerInput();
    document.getElementById('ai_base_url_field').style.display = p && p.value === 'custom' ? '' : 'none';
    var link = document.getElementById('ai_key_link'), url = p ? p.getAttribute('data-key-url') : '';
    link.style.display = url ? '' : 'none';
    link.href = url || '#';
  }
  form.querySelectorAll('input[name=ai_provider]').forEach(function (r) {
    r.addEventListener('change', function () { syncProvider(); setStatus('', 'เปลี่ยนผู้ให้บริการแล้ว — กดทดสอบเพื่อโหลดโมเดลใหม่'); });
  });
  syncProvider();

  document.getElementById('ai_key_toggle').addEventListener('click', function () {
    var k = document.getElementById('ai_api_key');
    k.type = k.type === 'password' ? 'text' : 'password';
  });

  function setStatus(kind, text) { status.className = 'ai-status' + (kind ? ' is-' + kind : ''); status.textContent = text; }

  function conn() {
    var fd = new FormData(form), o = {};
    ['ai_provider', 'ai_base_url', 'ai_api_key', 'ai_clear_key'].forEach(function (k) { if (fd.has(k)) o[k] = fd.get(k); });
    return o;
  }

  function api(body) {
    return fetch('ai_api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign(conn(), body)) })
      .then(function (r) { return r.json().catch(function () { return { error: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง' }; }).then(function (j) { if (!r.ok || j.error) throw new Error(j.error || 'HTTP ' + r.status); return j; }); });
  }

  function isOn(id) { return enabledModels.indexOf(id) !== -1; }

  function render() {
    var q = search.value.trim().toLowerCase();
    box.querySelectorAll('.ai-model').forEach(function (el) { el.remove(); });
    var shown = 0;
    all.forEach(function (m) {
      if (q && (m.id + ' ' + m.name).toLowerCase().indexOf(q) === -1) return;
      if (onlyOn.checked && !isOn(m.id)) return;
      if (++shown > 300) return;
      var row = document.createElement('div');
      row.className = 'ai-model' + (isOn(m.id) ? ' on' : '');
      row.innerHTML = '<label class="ai-model-main"><input type="checkbox"><span><strong></strong><small></small></span></label>' +
        '<span class="ai-model-result"></span><button type="button" class="ai-ghost ai-test">ทดสอบ</button>' +
        '<button type="button" class="ai-star" title="ตั้งเป็นโมเดลเริ่มต้น">★</button>';
      row.querySelector('strong').textContent = m.name !== m.id ? m.name : m.id;
      row.querySelector('small').textContent = m.name !== m.id ? m.id : '';
      var cb = row.querySelector('input');
      cb.checked = isOn(m.id);
      cb.addEventListener('change', function () {
        if (cb.checked) { enabledModels.push(m.id); if (!defaultModel) defaultModel = m.id; }
        else { enabledModels = enabledModels.filter(function (x) { return x !== m.id; }); if (defaultModel === m.id) defaultModel = enabledModels[0] || ''; }
        render();
      });
      var star = row.querySelector('.ai-star');
      if (defaultModel === m.id) star.classList.add('active');
      star.addEventListener('click', function () { if (!isOn(m.id)) enabledModels.push(m.id); defaultModel = m.id; render(); });
      var res = row.querySelector('.ai-model-result');
      row.querySelector('.ai-test').addEventListener('click', function (e) {
        var b = e.currentTarget; b.disabled = true; res.className = 'ai-model-result'; res.textContent = 'กำลังทดสอบ…';
        api({ action: 'test', model: m.id }).then(function (r) { res.className = 'ai-model-result ok'; res.textContent = '✓ ' + r.ms + ' ms'; })
          .catch(function (err) { res.className = 'ai-model-result bad'; res.textContent = '✗ ไม่ผ่าน'; res.title = err.message; })
          .then(function () { b.disabled = false; });
      });
      box.appendChild(row);
    });
    empty.style.display = all.length ? 'none' : '';
    count.textContent = 'เปิดใช้ ' + enabledModels.length + ' / ทั้งหมด ' + all.length + (shown > 300 ? ' (แสดง 300 รายการแรก — ใช้ค้นหา)' : '');
  }
  search.addEventListener('input', render);
  onlyOn.addEventListener('change', render);
  render();

  connectBtn.addEventListener('click', function () {
    connectBtn.disabled = true;
    setStatus('busy', 'กำลังเชื่อมต่อ…');
    api({ action: 'models' }).then(function (r) {
      var seen = {};
      all = r.models.slice();
      all.forEach(function (m) { seen[m.id] = true; });
      enabledModels.forEach(function (id) { if (!seen[id]) all.unshift({ id: id, name: id + ' (ไม่พบในรายการ)' }); });
      setStatus('ok', '✓ เชื่อมต่อสำเร็จ พบ ' + r.models.length + ' โมเดล');
      render();
    }).catch(function (err) { setStatus('bad', '✗ ' + err.message); })
      .then(function () { connectBtn.disabled = false; });
  });

  form.addEventListener('submit', function (e) {
    var enabled = document.querySelector('input[name=ai_enabled]').checked;
    if (enabled && !enabledModels.length) { e.preventDefault(); setStatus('bad', 'กรุณาเปิดใช้งานอย่างน้อย 1 โมเดล'); return; }
    form.querySelectorAll('.ai-hidden').forEach(function (el) { el.remove(); });
    enabledModels.forEach(function (id) { add('ai_models[]', id); });
    add('ai_model', defaultModel);
    var btn = document.getElementById('ai_save'); btn.disabled = true; btn.textContent = 'กำลังทดสอบ…';
    function add(n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; i.className = 'ai-hidden'; form.appendChild(i); }
  });
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
