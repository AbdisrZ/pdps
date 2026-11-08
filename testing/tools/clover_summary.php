<?php
/**
 * Ringkas clover.xml per direktori + per file + per metode (line coverage PCOV).
 * Usage: php clover_summary.php <clover.xml> <project-root> [out-dir]
 */
[$_, $clover, $root] = $argv;
$out = $argv[3] ?? null;
$root = str_replace('\\', '/', rtrim($root, '/\\')).'/';
$dirs = [
    'Shop/src/Http/Controllers/API',
    'Shop/src/Http/Requests',
    'Checkout/src',
    'Product/src/Type',
    'CartRule/src/Helpers',
];
$xml = simplexml_load_file($clover);
$agg = [];
$files = [];
$methods = [];
foreach ($xml->xpath('//file') as $f) {
    $path = str_replace('\\', '/', (string) $f['name']);
    $rel = str_replace($root, '', $path);
    $rel = preg_replace('~^packages/Webkul/~', '', $rel);
    $dir = null;
    foreach ($dirs as $d) {
        if (str_starts_with($rel, $d.'/')) {
            $dir = $d;
        }
    }
    $m = $f->metrics;
    $row = [
        'st' => (int) $m['statements'], 'cst' => (int) $m['coveredstatements'],
        'me' => (int) $m['methods'], 'cme' => (int) $m['coveredmethods'],
    ];
    $files[$rel] = $row + ['dir' => $dir];
    foreach (['st', 'cst', 'me', 'cme'] as $k) {
        $agg[$dir][$k] = ($agg[$dir][$k] ?? 0) + $row[$k];
    }
    $agg[$dir]['n'] = ($agg[$dir]['n'] ?? 0) + 1;

    // per method: clover <line type="method" name=.. count=..> lalu stmt sampai method berikutnya
    $cur = null;
    foreach ($f->line as $l) {
        if ((string) $l['type'] === 'method') {
            $cur = $rel.'::'.$l['name'].'()';
            $methods[$cur] = ['line' => (int) $l['num'], 'st' => 0, 'cst' => 0];
        } elseif ($cur && (string) $l['type'] === 'stmt') {
            $methods[$cur]['st']++;
            if ((int) $l['count'] > 0) {
                $methods[$cur]['cst']++;
            }
        }
    }
}
$pct = fn ($a, $b) => $b ? sprintf('%.2f%%', 100 * $a / $b) : 'n/a';

$o = "| Direktori (packages/Webkul/...) | File | Statement (baris) tertutup/total | % | Method tertutup/total | % |\n|---|---|---|---|---|---|\n";
$T = ['st' => 0, 'cst' => 0, 'me' => 0, 'cme' => 0, 'n' => 0];
foreach ($dirs as $d) {
    $a = $agg[$d] ?? ['st' => 0, 'cst' => 0, 'me' => 0, 'cme' => 0, 'n' => 0];
    foreach ($T as $k => $_) {
        $T[$k] += $a[$k];
    }
    $o .= "| `$d` | {$a['n']} | {$a['cst']}/{$a['st']} | {$pct($a['cst'],$a['st'])} | {$a['cme']}/{$a['me']} | {$pct($a['cme'],$a['me'])} |\n";
}
$o .= "| **Total** | {$T['n']} | {$T['cst']}/{$T['st']} | **{$pct($T['cst'],$T['st'])}** | {$T['cme']}/{$T['me']} | **{$pct($T['cme'],$T['me'])}** |\n";
if (isset($agg[null])) {
    $o .= "\n(file di luar 5 direktori: {$agg[null]['n']})\n";
}

$f = "| File | Statement | % | Method | % |\n|---|---|---|---|---|\n";
ksort($files);
foreach ($files as $rel => $r) {
    $f .= "| `$rel` | {$r['cst']}/{$r['st']} | {$pct($r['cst'],$r['st'])} | {$r['cme']}/{$r['me']} | {$pct($r['cme'],$r['me'])} |\n";
}

$mm = "| Method | Baris | Statement | % |\n|---|---|---|---|\n";
foreach ($methods as $name => $r) {
    $mm .= "| `$name` | {$r['line']} | {$r['cst']}/{$r['st']} | {$pct($r['cst'],$r['st'])} |\n";
}

echo $o;
if ($out) {
    @mkdir($out, 0777, true);
    file_put_contents("$out/ringkasan-direktori.md", $o);
    file_put_contents("$out/ringkasan-file.md", $f);
    file_put_contents("$out/ringkasan-method.md", $mm);
    file_put_contents("$out/ringkasan.json", json_encode(['dirs' => $agg, 'files' => $files, 'methods' => $methods], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
