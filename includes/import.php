<?php
declare(strict_types=1);

/**
 * Parsers for common question-bank formats. Each returns a normalized array of:
 *   ['type' => 'mc'|'checkbox', 'text' => string, 'scored' => true,
 *    'options' => [['label' => string, 'is_correct' => bool], ...]]
 */

function import_parse_aiken(string $content): array
{
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $questions = [];
    $current = null;

    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^([A-Za-z])\)\s*(.+)$/', $line, $m)) {
            if ($current !== null) {
                $current['options'][] = ['key' => strtoupper($m[1]), 'label' => trim($m[2]), 'is_correct' => false];
            }
        } elseif (preg_match('/^ANSWER\s*:\s*([A-Za-z])/i', $line, $m)) {
            if ($current !== null) {
                $answerKey = strtoupper($m[1]);
                foreach ($current['options'] as &$opt) {
                    if ($opt['key'] === $answerKey) {
                        $opt['is_correct'] = true;
                    }
                }
                unset($opt);
                $questions[] = normalize_question($current, 'mc');
                $current = null;
            }
        } else {
            if ($current !== null) {
                $questions[] = normalize_question($current, 'mc');
            }
            $current = ['text' => trim($line), 'options' => []];
        }
    }

    return $questions;
}

function import_parse_gift(string $content): array
{
    // Strip comment lines and blank-collapse.
    $content = preg_replace('/^\s*\/\/.*$/m', '', $content);
    preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $content, $matches, PREG_SET_ORDER);

    $questions = [];
    foreach ($matches as $m) {
        $text = trim(preg_replace('/^::[^:]*::/', '', trim($m[1])));
        if ($text === '') {
            continue;
        }
        $body = trim($m[2]);
        if ($body === '') {
            continue;
        }

        $options = [];
        // Split on ~ or = tokens while keeping the delimiter.
        preg_match_all('/([=~])\s*(%-?\d+%)?\s*([^~=]+)/', $body, $optMatches, PREG_SET_ORDER);
        foreach ($optMatches as $om) {
            $label = trim($om[3]);
            if ($label === '') {
                continue;
            }
            $options[] = ['label' => $label, 'is_correct' => $om[1] === '='];
        }

        if (empty($options)) {
            continue;
        }

        $questions[] = normalize_question(['text' => $text, 'options' => $options], 'mc');
    }

    return $questions;
}

function import_parse_csv(string $content): array
{
    $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($content)));
    $questions = [];

    foreach ($rows as $i => $row) {
        if ($i === 0 && isset($row[0]) && stripos($row[0], 'question') !== false) {
            continue; // header row
        }
        if (count($row) < 3) {
            continue;
        }

        $text = trim((string) $row[0]);
        $type = strtolower(trim((string) $row[1]));
        if (!in_array($type, ['mc', 'checkbox', 'dropdown'], true)) {
            $type = 'mc';
        }
        $correctIndexes = array_map('trim', explode(';', (string) end($row)));
        $optionCells = array_slice($row, 2, count($row) - 3);

        $options = [];
        foreach ($optionCells as $idx => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $options[] = ['label' => $label, 'is_correct' => in_array((string) ($idx + 1), $correctIndexes, true)];
        }

        if ($text === '' || empty($options)) {
            continue;
        }

        $questions[] = normalize_question(['text' => $text, 'options' => $options], $type);
    }

    return $questions;
}

function normalize_question(array $q, string $type): array
{
    $options = array_map(static fn ($o) => ['label' => $o['label'], 'is_correct' => (bool) $o['is_correct']], $q['options']);
    return [
        'type' => $type,
        'text' => $q['text'],
        'scored' => true,
        'options' => $options,
    ];
}

function import_detect_and_parse(string $content, string $filename): array
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        return import_parse_csv($content);
    }
    if ($ext === 'gift' || str_contains($content, '{') && str_contains($content, '}')) {
        $parsed = import_parse_gift($content);
        if (!empty($parsed)) {
            return $parsed;
        }
    }
    return import_parse_aiken($content);
}
