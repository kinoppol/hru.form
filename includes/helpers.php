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

/** Thai five-level interpretation of a 1–5 mean (Best, 1977 criteria). */
function scale_mean_label(float $mean): string
{
    if ($mean >= 4.51) { return 'มากที่สุด'; }
    if ($mean >= 3.51) { return 'มาก'; }
    if ($mean >= 2.51) { return 'ปานกลาง'; }
    if ($mean >= 1.51) { return 'น้อย'; }
    return 'น้อยที่สุด';
}

/** @return array{n:int,mean:float,sd:float} sample standard deviation (n-1), as used in Thai survey reports */
function mean_sd(array $values): array
{
    $n = count($values);
    if ($n === 0) { return ['n' => 0, 'mean' => 0.0, 'sd' => 0.0]; }
    $mean = array_sum($values) / $n;
    $sq = 0.0;
    foreach ($values as $v) { $sq += ($v - $mean) ** 2; }
    return ['n' => $n, 'mean' => $mean, 'sd' => $n > 1 ? sqrt($sq / ($n - 1)) : 0.0];
}

/**
 * Per-question summary of a form's responses.
 * scale → mean/SD/distribution; mc/dropdown/checkbox → option counts; short → answer count.
 */
function survey_summary(int $formId): array
{
    $qs = db()->prepare('SELECT id, type, text FROM questions WHERE form_id = ? ORDER BY sort_order, id');
    $qs->execute([$formId]);
    $questions = $qs->fetchAll();
    if (!$questions) { return []; }

    $os = db()->prepare('SELECT o.id, o.question_id, o.label FROM question_options o JOIN questions q ON q.id = o.question_id WHERE q.form_id = ? ORDER BY o.sort_order, o.id');
    $os->execute([$formId]);
    $options = [];
    foreach ($os->fetchAll() as $o) { $options[(int) $o['question_id']][(int) $o['id']] = $o['label']; }

    $as = db()->prepare('SELECT a.question_id, a.answer_json FROM response_answers a JOIN form_responses r ON r.id = a.response_id WHERE r.form_id = ?');
    $as->execute([$formId]);
    $answers = [];
    foreach ($as->fetchAll() as $a) { $answers[(int) $a['question_id']][] = json_decode((string) $a['answer_json'], true); }

    $out = [];
    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $row = ['id' => $qid, 'type' => $q['type'], 'text' => $q['text']];
        $list = $answers[$qid] ?? [];
        if ($q['type'] === 'scale') {
            $vals = [];
            $dist = array_fill(1, 5, 0);
            foreach ($list as $v) {
                if (is_numeric($v) && (int) $v >= 1 && (int) $v <= 5) { $vals[] = (int) $v; $dist[(int) $v]++; }
            }
            $row += mean_sd($vals) + ['dist' => $dist];
        } elseif ($q['type'] === 'short') {
            $row['n'] = count(array_filter($list, static fn ($v) => is_string($v) && trim($v) !== ''));
        } else {
            $counts = array_fill_keys(array_keys($options[$qid] ?? []), 0);
            $n = 0;
            foreach ($list as $v) {
                $picked = is_array($v) ? $v : ($v === null ? [] : [$v]);
                if ($picked) { $n++; }
                foreach ($picked as $oid) { if (isset($counts[(int) $oid])) { $counts[(int) $oid]++; } }
            }
            $row['n'] = $n;
            $row['options'] = [];
            foreach ($counts as $oid => $c) { $row['options'][] = ['label' => $options[$qid][$oid], 'count' => $c]; }
        }
        $out[] = $row;
    }
    return $out;
}
