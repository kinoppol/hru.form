<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Preset base URLs for OpenAI-compatible chat-completions providers. */
function ai_providers(): array
{
    return [
        'openrouter' => ['label' => 'OpenRouter', 'hint' => 'รวมหลายโมเดลในที่เดียว', 'base_url' => 'https://openrouter.ai/api/v1', 'key_url' => 'https://openrouter.ai/keys'],
        'google' => ['label' => 'Google AI Studio', 'hint' => 'Gemini โดยตรงจาก Google', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'key_url' => 'https://aistudio.google.com/apikey'],
        'custom' => ['label' => 'LLM Server อื่น ๆ', 'hint' => 'Ollama, LM Studio, vLLM ฯลฯ', 'base_url' => '', 'key_url' => ''],
    ];
}

function ai_settings(): array
{
    $s = ['ai_enabled' => '0', 'ai_provider' => 'openrouter', 'ai_base_url' => '', 'ai_api_key' => '', 'ai_model' => '', 'ai_models' => '[]'];
    try {
        $rows = db()->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('ai_enabled','ai_provider','ai_base_url','ai_api_key','ai_model','ai_models')")->fetchAll();
        foreach ($rows as $r) { $s[$r['key']] = (string) $r['value']; }
    } catch (Throwable $e) {
        // settings table unavailable; keep defaults
    }
    return $s;
}

/**
 * Build an unsaved connection config from admin input. A blank API key falls back
 * to the stored key so admins can re-test without pasting it again.
 */
function ai_config_from_input(array $in): array
{
    $stored = ai_settings();
    $provider = (string) ($in['ai_provider'] ?? 'openrouter');
    if (!array_key_exists($provider, ai_providers())) { $provider = 'openrouter'; }
    $key = trim((string) ($in['ai_api_key'] ?? ''));
    if ($key === '' && empty($in['ai_clear_key'])) { $key = $stored['ai_api_key']; }
    return array_merge($stored, [
        'ai_provider' => $provider,
        'ai_base_url' => trim((string) ($in['ai_base_url'] ?? '')),
        'ai_api_key' => $key,
    ]);
}

/** Models the admin has enabled, default model first. */
function ai_enabled_models(?array $s = null): array
{
    $s = $s ?? ai_settings();
    $models = json_decode($s['ai_models'], true);
    $models = is_array($models) ? array_values(array_filter($models, 'is_string')) : [];
    if ($s['ai_model'] !== '') {
        $models = array_values(array_unique(array_merge([$s['ai_model']], $models)));
    }
    return $models;
}

function ai_base_url(array $s): string
{
    $base = $s['ai_provider'] === 'custom' ? $s['ai_base_url'] : (ai_providers()[$s['ai_provider']]['base_url'] ?? '');
    return rtrim($base, '/');
}

function ai_is_enabled(): bool
{
    $s = ai_settings();
    return $s['ai_enabled'] === '1' && $s['ai_model'] !== '' && ai_base_url($s) !== '';
}

function ai_system_prompt(): string
{
    return <<<'TXT'
คุณคือผู้ช่วย AI ของระบบสร้างแบบสอบถามและแบบทดสอบ ตอบเป็นภาษาไทยอย่างกระชับและเป็นมิตร
หน้าที่: ช่วยผู้ใช้คิด ร่าง และปรับปรุงแบบสอบถาม (survey) หรือแบบทดสอบ (quiz)

ชนิดคำถามที่ระบบรองรับ:
- mc = เลือกตอบข้อเดียว (ให้คะแนนได้ ระบุ is_correct)
- checkbox = เลือกได้หลายข้อ (ให้คะแนนได้)
- dropdown = เลือกจากรายการ (ให้คะแนนได้)
- scale = ระดับความเห็น 1-5 (ไม่ต้องมี options)
- short = คำตอบปลายเปิด (ไม่ต้องมี options)

เมื่อผู้ใช้ขอให้ร่างหรือแก้ไขแบบสอบถาม/แบบทดสอบ ให้อธิบายสั้น ๆ แล้วแนบร่างฉบับเต็มเป็นโค้ดบล็อก ```json เพียงบล็อกเดียว ตามโครงสร้างนี้:
{"title":"ชื่อฟอร์ม","questions":[{"type":"mc","text":"คำถาม","options":[{"label":"ตัวเลือก","is_correct":true}]}]}
แบบสอบถามความคิดเห็นให้ is_correct เป็น false ทั้งหมด ส่วนแบบทดสอบต้องมีคำตอบที่ถูกอย่างน้อยหนึ่งข้อต่อคำถาม
ผู้ใช้สามารถกดปุ่มเพื่อสร้างฟอร์มจากร่างนั้นได้ทันที
TXT;
}

/**
 * Low-level HTTP call to an OpenAI-compatible endpoint.
 * @return array{0:bool,1:mixed} [ok, decoded-json or error message]
 */
function ai_http(array $s, string $path, ?array $payload = null, int $timeout = 120): array
{
    $base = ai_base_url($s);
    if ($base === '') { return [false, 'ยังไม่ได้ระบุ Base URL']; }
    $headers = ['Accept: application/json'];
    if ($payload !== null) { $headers[] = 'Content-Type: application/json'; }
    if ($s['ai_api_key'] !== '') { $headers[] = 'Authorization: Bearer ' . $s['ai_api_key']; }
    if ($s['ai_provider'] === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . app_base_url();
        $headers[] = 'X-Title: ' . app_name();
    }
    $ch = curl_init($base . $path);
    $opts = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) { return [false, 'เชื่อมต่อไม่สำเร็จ: ' . $err]; }
    $data = json_decode((string) $body, true);
    if ($code >= 400 || !is_array($data)) {
        $msg = '';
        if (is_array($data)) { $msg = (string) ($data['error']['message'] ?? $data[0]['error']['message'] ?? ''); }
        if ($code === 401 || $code === 403) { $msg = 'API key ไม่ถูกต้องหรือไม่มีสิทธิ์' . ($msg !== '' ? ' (' . $msg . ')' : ''); }
        return [false, 'HTTP ' . $code . ($msg !== '' ? ': ' . $msg : '')];
    }
    return [true, $data];
}

/** @return array{0:bool,1:array|string} [ok, list of ['id','name'] or error] */
function ai_fetch_models(array $s): array
{
    [$ok, $data] = ai_http($s, '/models', null, 30);
    if (!$ok) { return [false, $data]; }
    $models = [];
    foreach ((array) ($data['data'] ?? $data['models'] ?? []) as $m) {
        $id = is_array($m) ? (string) ($m['id'] ?? $m['name'] ?? '') : (string) $m;
        if ($id === '') { continue; }
        $id = preg_replace('#^models/#', '', $id); // Google prefixes ids with "models/"
        $models[$id] = ['id' => $id, 'name' => is_array($m) ? (string) ($m['name'] ?? $m['display_name'] ?? $id) : $id];
    }
    ksort($models, SORT_NATURAL | SORT_FLAG_CASE);
    return empty($models) ? [false, 'ไม่พบรายการโมเดลจากผู้ให้บริการนี้'] : [true, array_values($models)];
}

/** @return array{0:bool,1:string} [ok, reply-or-error] */
function ai_chat(array $messages, ?string $model = null, ?array $s = null, bool $withSystem = true): array
{
    $s = $s ?? ai_settings();
    $model = $model ?? $s['ai_model'];
    if ($model === '') { return [false, 'ยังไม่ได้เลือกโมเดล']; }
    $payload = [
        'model' => $model,
        'messages' => $withSystem ? array_merge([['role' => 'system', 'content' => ai_system_prompt()]], $messages) : $messages,
        'temperature' => 0.7,
    ];
    [$ok, $data] = ai_http($s, '/chat/completions', $payload);
    if (!$ok) { return [false, 'AI ตอบกลับผิดพลาด: ' . $data]; }
    $reply = (string) ($data['choices'][0]['message']['content'] ?? '');
    return $reply === '' ? [false, 'AI ไม่ได้ส่งคำตอบกลับมา'] : [true, $reply];
}

/** Insert an AI draft ({title, questions[]}) as a new draft form owned by $userId. Returns the form id. */
function ai_create_form_from_draft(int $userId, array $draft): int
{
    $title = trim((string) ($draft['title'] ?? ''));
    if ($title === '') { $title = 'แบบฟอร์มจากผู้ช่วย AI'; }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO forms (user_id, title, status, share_token, personal_fields_json) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, mb_substr($title, 0, 255), 'draft', generate_share_token(), json_encode(default_personal_fields(), JSON_UNESCAPED_UNICODE)]);
    $formId = (int) $pdo->lastInsertId();

    $qIns = $pdo->prepare('INSERT INTO questions (form_id, type, text, scored, sort_order) VALUES (?, ?, ?, ?, ?)');
    $oIns = $pdo->prepare('INSERT INTO question_options (question_id, label, is_correct, sort_order) VALUES (?, ?, ?, ?)');
    $order = 0;
    foreach ((array) ($draft['questions'] ?? []) as $q) {
        if (!is_array($q)) { continue; }
        $text = trim((string) ($q['text'] ?? ''));
        if ($text === '') { continue; }
        $type = (string) ($q['type'] ?? 'mc');
        if (!in_array($type, ['mc', 'checkbox', 'dropdown', 'scale', 'short'], true)) { $type = 'mc'; }
        $hasOptions = in_array($type, ['mc', 'checkbox', 'dropdown'], true);
        $qIns->execute([$formId, $type, $text, $hasOptions ? 1 : 0, $order++]);
        $qid = (int) $pdo->lastInsertId();
        if (!$hasOptions) { continue; }
        $j = 0;
        foreach ((array) ($q['options'] ?? []) as $opt) {
            $label = trim((string) (is_array($opt) ? ($opt['label'] ?? '') : $opt));
            if ($label === '') { continue; }
            $oIns->execute([$qid, $label, (is_array($opt) && !empty($opt['is_correct'])) ? 1 : 0, $j++]);
        }
    }
    $pdo->commit();
    return $formId;
}
