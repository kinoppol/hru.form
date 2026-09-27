<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json; charset=utf-8');
ob_start(); // stray warnings/notices must not corrupt the JSON body
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(500);
        echo json_encode(['error' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e['message']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
});

function api_json(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!auth_check()) { api_json(['error' => 'กรุณาเข้าสู่ระบบผู้ดูแล'], 401); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    api_json(['error' => 'คำขอไม่ถูกต้อง'], 400);
}
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) { api_json(['error' => 'ข้อมูลไม่ถูกต้อง'], 400); }

$s = ai_config_from_input($input);
session_write_close();
set_time_limit(150);

switch ($input['action'] ?? '') {
    case 'models':
        [$ok, $res] = ai_fetch_models($s);
        $ok ? api_json(['models' => $res]) : api_json(['error' => $res], 502);
        break;
    case 'test':
        $model = trim((string) ($input['model'] ?? ''));
        if ($model === '') { api_json(['error' => 'กรุณาเลือกโมเดล'], 400); }
        $t = microtime(true);
        [$ok, $res] = ai_chat([['role' => 'user', 'content' => 'Reply with exactly: OK']], $model, $s, false);
        $ok ? api_json(['reply' => mb_substr($res, 0, 120), 'ms' => (int) ((microtime(true) - $t) * 1000)]) : api_json(['error' => $res], 502);
        break;
    default:
        api_json(['error' => 'ไม่รู้จักคำสั่ง'], 400);
}
