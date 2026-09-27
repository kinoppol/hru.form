<?php
declare(strict_types=1);

// Export every response of a form (personal data, score, answers) as an .xlsx workbook.

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

$qs = db()->prepare('SELECT id, type, text FROM questions WHERE form_id = ? ORDER BY sort_order, id');
$qs->execute([$formId]);
$questions = $qs->fetchAll();

$os = db()->prepare('SELECT o.id, o.label FROM question_options o JOIN questions q ON q.id = o.question_id WHERE q.form_id = ?');
$os->execute([$formId]);
$optionLabels = [];
foreach ($os->fetchAll() as $o) { $optionLabels[(int) $o['id']] = $o['label']; }

$rs = db()->prepare('SELECT id, score, max_score, submitted_at, personal_data_json FROM form_responses WHERE form_id = ? ORDER BY submitted_at ASC, id ASC');
$rs->execute([$formId]);
$responses = $rs->fetchAll();

$as = db()->prepare('SELECT a.response_id, a.question_id, a.answer_json FROM response_answers a JOIN form_responses r ON r.id = a.response_id WHERE r.form_id = ?');
$as->execute([$formId]);
$answers = [];
foreach ($as->fetchAll() as $a) {
    $answers[(int) $a['response_id']][(int) $a['question_id']] = json_decode((string) $a['answer_json'], true);
}

// header row
$header = ['#'];
foreach ($personalFields as $pf) { $header[] = (string) $pf['label']; }
if ($form['show_score']) { $header[] = 'คะแนน'; $header[] = 'คะแนนเต็ม'; }
$header[] = 'วันที่ส่ง';
foreach ($questions as $i => $q) { $header[] = ($i + 1) . '. ' . $q['text']; }
$rows = [$header];

foreach ($responses as $n => $r) {
    $pd = json_decode($r['personal_data_json'] ?? '{}', true) ?: [];
    $row = [$n + 1];
    foreach ($personalFields as $pf) { $row[] = (string) ($pd[$pf['key']] ?? ''); }
    if ($form['show_score']) { $row[] = (int) $r['score']; $row[] = (int) $r['max_score']; }
    $row[] = (string) $r['submitted_at'];
    foreach ($questions as $q) {
        $v = $answers[(int) $r['id']][(int) $q['id']] ?? null;
        if ($q['type'] === 'scale') {
            $row[] = is_numeric($v) ? (int) $v : '';
        } elseif ($q['type'] === 'short') {
            $row[] = is_string($v) ? $v : '';
        } else {
            $picked = is_array($v) ? $v : ($v === null ? [] : [$v]);
            $row[] = implode(', ', array_map(static fn ($oid) => $optionLabels[(int) $oid] ?? '', $picked));
        }
    }
    $rows[] = $row;
}

// ---- build a minimal xlsx (SpreadsheetML in a zip) ----
$x = static fn (string $s): string => htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
$col = static function (int $i): string {
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) { $s = chr(65 + ($i - 1) % 26) . $s; }
    return $s;
};

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
    . '<sheetData>';
foreach ($rows as $ri => $row) {
    $sheet .= '<row r="' . ($ri + 1) . '">';
    foreach ($row as $ci => $v) {
        $ref = $col($ci) . ($ri + 1);
        $style = $ri === 0 ? ' s="1"' : '';
        if (is_int($v)) {
            $sheet .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
        } else {
            $sheet .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
        }
    }
    $sheet .= '</row>';
}
$sheet .= '</sheetData></worksheet>';

$sheetName = 'คำตอบ';

// Pure-PHP zip writer (deflate when zlib is available, else stored) so no ZipArchive extension is required.
$zip = new class {
    private string $data = '';
    private string $dir = '';
    private int $count = 0;
    public function addFromString(string $name, string $content): void
    {
        $method = function_exists('gzdeflate') ? 8 : 0;
        $body = $method ? gzdeflate($content, 6) : $content;
        $head = pack('vvvvvVVVvv', 20, 0x0800, $method, 0, 0x21, crc32($content), strlen($body), strlen($content), strlen($name), 0);
        $offset = strlen($this->data);
        $this->data .= "PK\x03\x04" . $head . $name . $body;
        $this->dir .= "PK\x01\x02" . pack('v', 20) . $head . pack('vvvVV', 0, 0, 0, 0, $offset) . $name;
        $this->count++;
    }
    public function output(): string
    {
        return $this->data . $this->dir . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $this->count, $this->count, strlen($this->dir), strlen($this->data), 0);
    }
};
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="' . $sheetName . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>');
$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><name val="Tahoma"/></font></fonts>'
    . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
    . '<borders count="1"><border/></borders>'
    . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
    . '<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs>'
    . '</styleSheet>');
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$bin = $zip->output();

$filename = 'responses-' . $formId . '-' . date('Ymd-His') . '.xlsx';
$utfName  = preg_replace('/[\\\\\/:*?"<>|\r\n]/u', '_', $form['title']) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($utfName));
header('Content-Length: ' . strlen($bin));
echo $bin;
