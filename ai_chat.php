<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/ai.php';

header('Content-Type: application/json; charset=utf-8');

function ai_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!site_check()) { ai_json(['error' => 'กรุณาเข้าสู่ระบบ'], 401); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ai_json(['error' => 'Method not allowed'], 405); }
// JSON content type blocks simple cross-site form posts (no preflight-free CSRF).
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) { ai_json(['error' => 'ข้อมูลไม่ถูกต้อง'], 400); }
if (!ai_is_enabled()) { ai_json(['error' => 'ผู้ดูแลระบบยังไม่ได้เปิดใช้งานผู้ช่วย AI'], 503); }

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) { ai_json(['error' => 'ข้อมูลไม่ถูกต้อง'], 400); }

if (($input['action'] ?? 'chat') === 'create_form') {
    $draft = $input['draft'] ?? null;
    if (!is_array($draft) || empty($draft['questions']) || !is_array($draft['questions'])) {
        ai_json(['error' => 'ร่างแบบฟอร์มไม่ถูกต้อง'], 400);
    }
    $formId = ai_create_form_from_draft((int) site_user_id(), $draft);
    ai_json(['form_id' => $formId, 'url' => 'form_edit.php?id=' . $formId]);
}

// Keep only the most recent user/assistant turns, each capped in length.
$messages = [];
foreach (array_slice((array) ($input['messages'] ?? []), -20) as $m) {
    if (!is_array($m) || !in_array($m['role'] ?? '', ['user', 'assistant'], true)) { continue; }
    $content = mb_substr(trim((string) ($m['content'] ?? '')), 0, 8000);
    if ($content !== '') { $messages[] = ['role' => $m['role'], 'content' => $content]; }
}
if (empty($messages)) { ai_json(['error' => 'กรุณาพิมพ์ข้อความ'], 400); }

session_write_close(); // don't block the user's other requests while waiting on the LLM
$allowed = ai_enabled_models();
$model = (string) ($input['model'] ?? '');
if (!in_array($model, $allowed, true)) { $model = $allowed[0] ?? null; }
[$ok, $reply] = ai_chat($messages, $model);
$ok ? ai_json(['reply' => $reply]) : ai_json(['error' => $reply], 502);
