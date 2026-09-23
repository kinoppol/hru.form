</main>
<?php
// Floating AI assistant: only for logged-in form builders, never on public respondent pages.
if (!isset($navBrand) && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['f.php', 'results.php'], true) && function_exists('site_check') && site_check()):
    require_once __DIR__ . '/ai.php';
    if (ai_is_enabled()):
        $aiPrefix = isset($assetPrefix) ? h($assetPrefix) : '';
?>
<div id="ai-assist" data-endpoint="<?php echo $aiPrefix; ?>ai_chat.php" data-prefix="<?php echo $aiPrefix; ?>">
  <section class="ai-panel" role="dialog" aria-label="ผู้ช่วย AI">
    <header class="ai-head">
      <span class="ai-head-title"><span class="ai-spark">✦</span> ผู้ช่วย AI</span>
      <button type="button" class="ai-icon-btn ai-reset" title="เริ่มบทสนทนาใหม่">↺</button>
      <button type="button" class="ai-icon-btn ai-close" title="ปิด">✕</button>
    </header>
    <?php $aiModelList = ai_enabled_models(); if (count($aiModelList) > 1): ?>
    <div class="ai-model-bar"><label for="ai-model-select">โมเดล</label><select id="ai-model-select"><?php foreach ($aiModelList as $m): ?><option value="<?php echo h($m); ?>"><?php echo h($m); ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="ai-log" aria-live="polite"></div>
    <form class="ai-input">
      <textarea rows="1" placeholder="เช่น ร่างแบบทดสอบวิทยาศาสตร์ 10 ข้อ..." aria-label="ข้อความถึงผู้ช่วย AI"></textarea>
      <button type="submit" class="btn btn-primary" aria-label="ส่ง">➤</button>
    </form>
  </section>
  <button type="button" class="ai-fab" aria-expanded="false" aria-label="เปิดผู้ช่วย AI" title="ผู้ช่วย AI">
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 1.9 1.9.8-1.9.8L19 20.4l-.8-1.9-1.9-.8 1.9-.8z"/></svg>
  </button>
</div>
<script src="<?php echo $aiPrefix; ?>assets/js/ai-assistant.js" defer></script>
<?php endif; endif; ?>
</body>
</html>
