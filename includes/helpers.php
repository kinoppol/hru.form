<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string
    {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function generate_share_token(): string
{
    return substr(bin2hex(random_bytes(8)), 0, 12);
}

function default_personal_fields(): array
{
    return [
        ['key' => 'name', 'label' => 'ชื่อ-นามสกุล', 'type' => 'text', 'required' => true],
        ['key' => 'email', 'label' => 'อีเมล', 'type' => 'email', 'required' => true],
        ['key' => 'dept', 'label' => 'หน่วยงาน/แผนก', 'type' => 'text', 'required' => false],
    ];
}

function theme_colors(): array
{
    return [
        ['hex' => '#9184d9', 'label' => 'ม่วง (ค่าเริ่มต้น)'],
        ['hex' => '#5c9bd8', 'label' => 'ฟ้า'],
        ['hex' => '#4fbfab', 'label' => 'เขียวมิ้นท์'],
        ['hex' => '#d9a24f', 'label' => 'เหลืองอำพัน'],
        ['hex' => '#d97a95', 'label' => 'โรส'],
        ['hex' => '#93c15c', 'label' => 'เขียวมะนาว'],
    ];
}

function question_type_label(string $type): string
{
    return [
        'mc' => 'เลือกตอบข้อเดียว',
        'checkbox' => 'เลือกได้หลายข้อ',
        'dropdown' => 'เลือกจากรายการ',
        'scale' => 'ระดับความเห็น (ไม่ให้คะแนน)',
        'short' => 'คำตอบปลายเปิด (ไม่ให้คะแนน)',
    ][$type] ?? $type;
}

function app_base_url(): string
{
    $cfg = app_config();
    if (!empty($cfg['app']['url'])) {
        return rtrim($cfg['app']['url'], '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

function app_name(): string
{
    static $name = null;
    if ($name !== null) { return $name; }
    $name = 'Noema';
    if (function_exists('db')) {
        try {
            $v = db()->query("SELECT `value` FROM settings WHERE `key` = 'app_name'")->fetchColumn();
            if (is_string($v) && trim($v) !== '') { $name = trim($v); }
        } catch (Throwable $e) {
            // settings table unavailable; keep default
        }
    }
    return $name;
}
