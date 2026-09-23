<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json; charset=utf-8');

function api_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
