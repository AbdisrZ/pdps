<?php
/**
 * Buat tabel hasil eksekusi (Markdown) dari:
 *   - expected : kolom "Expected" pada tabel testing/04-blackbox.md (per ID)
 *   - actual   : evidence/F4-actual-run1/<ID>.json (diringkas)
 *   - status   : evidence/F4-ringkasan-status.tsv (hasil junit_ids.php, run1 & run2)
 * Usage: php gen_hasil.php <04-blackbox.md> <ringkasan-status.tsv> <dir-actual> [prefix,prefix,...]
 */
[$_, $blackbox, $tsv, $actualDir] = $argv;
$prefixes = isset($argv[4]) ? explode(',', $argv[4]) : null;

// 1. Expected dari 04-blackbox.md
$expected = [];
$header = null;
foreach (file($blackbox) as $line) {
    if (! str_starts_with($line, '|')) {
        $header = null;
        continue;
    }
    $cells = array_map('trim', explode('|', trim($line, " \n\r|")));
    if ($header === null) {
        $header = $cells;
        continue;
    }
    if (preg_match('/^-+$/', $cells[0] ?? '')) {
        continue;
    }
    if (preg_match('/^[A-Z]{2}-\d{2}$/', $cells[0] ?? '')) {
        $idx = array_search('Expected', $header, true);
        if ($idx !== false) {
            $expected[$cells[0]] = $cells[$idx];
        }
    }
}

// 2. Status
$status = [];
foreach (file($tsv) as $line) {
    $p = explode("\t", trim($line));
    if (count($p) >= 3) {
        $status[$p[0]] = [$p[1], $p[2]];
    }
}

// 3. Ringkas actual
$summ = function (string $id) use ($actualDir): string {
    $f = $actualDir.DIRECTORY_SEPARATOR.$id.'.json';
    if (! is_file($f)) {
        return '(tidak ada rekaman)';
    }
    $d = json_decode(file_get_contents($f), true);
    $parts = [];
    if (isset($d['status'])) {
        $parts[] = 'HTTP '.$d['status'];
    }
    $msg = $d['body']['message'] ?? ($d['body']['data']['message'] ?? null);
    if (is_string($msg) && $msg !== '') {
        $parts[] = '"'.mb_substr($msg, 0, 90).(mb_strlen($msg) > 90 ? '…' : '').'"';
    }
    if (isset($d['body']['errors']) && is_array($d['body']['errors'])) {
        $parts[] = 'error: '.implode(', ', array_keys($d['body']['errors']));
    }
    foreach ($d as $k => $v) {
        if (in_array($k, ['id', 'waktu', 'status', 'body', 'exception', 'input'], true)) {
            continue;
        }
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($v)) {
            $v = $v ? 'true' : 'false';
        } elseif ($v === null) {
            $v = 'null';
        }
        $parts[] = $k.'='.mb_substr((string) $v, 0, 80);
    }

    return str_replace('|', '\\|', implode('; ', $parts));
};

echo "| ID | Expected (04-blackbox) | Actual (run 1) | Run 1 | Run 2 | Status | Bukti |\n|---|---|---|---|---|---|---|\n";
uksort($status, 'strnatcmp');
foreach ($status as $id => [$r1, $r2]) {
    if ($id === 'GUARD-01') {
        continue;
    }
    if ($prefixes && ! in_array(explode('-', $id)[0], $prefixes, true)) {
        continue;
    }
    $final = ($r1 === 'PASS' && $r2 === 'PASS') ? 'PASS' : (($r1 !== 'PASS' && $r2 !== 'PASS') ? '**FAIL**' : 'TIDAK STABIL');
    printf("| %s | %s | %s | %s | %s | %s | `evidence/F4-actual-run1/%s.json` |\n",
        $id, $expected[$id] ?? '(lihat catatan)', $summ($id), $r1, $r2, $final, $id);
}
