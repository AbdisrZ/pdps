<?php
/**
 * Bandingkan dua ringkasan.json (hasil clover_summary.php): per direktori dan per method terpilih.
 * Usage: php coverage_compare.php <baseline/ringkasan.json> <after/ringkasan.json> [pola-method-regex]
 */
[$_, $baseFile, $afterFile] = $argv;
$pattern = $argv[3] ?? null;

$b = json_decode(file_get_contents($baseFile), true);
$a = json_decode(file_get_contents($afterFile), true);

$pct = fn ($c, $t) => $t ? sprintf('%.2f%%', 100 * $c / $t) : 'n/a';
$delta = fn ($c1, $t1, $c2, $t2) => $t1 && $t2 ? sprintf('%+.2f pp', 100 * ($c2 / $t2 - $c1 / $t1)) : 'n/a';

echo "| Direktori | Statement baseline | Statement sesudah | Δ | Method baseline | Method sesudah | Δ |\n|---|---|---|---|---|---|---|\n";
$T = ['b' => [0, 0, 0, 0], 'a' => [0, 0, 0, 0]];
foreach ($b['dirs'] as $dir => $r) {
    if ($dir === '') {
        continue;
    }
    $s = $a['dirs'][$dir];
    foreach ([[$r, 'b'], [$s, 'a']] as [$x, $k]) {
        $T[$k][0] += $x['cst']; $T[$k][1] += $x['st']; $T[$k][2] += $x['cme']; $T[$k][3] += $x['me'];
    }
    printf("| `%s` | %d/%d (%s) | %d/%d (%s) | %s | %d/%d (%s) | %d/%d (%s) | %s |\n",
        $dir, $r['cst'], $r['st'], $pct($r['cst'], $r['st']), $s['cst'], $s['st'], $pct($s['cst'], $s['st']),
        $delta($r['cst'], $r['st'], $s['cst'], $s['st']),
        $r['cme'], $r['me'], $pct($r['cme'], $r['me']), $s['cme'], $s['me'], $pct($s['cme'], $s['me']),
        $delta($r['cme'], $r['me'], $s['cme'], $s['me']));
}
[$c1, $t1, $m1, $n1] = $T['b'];
[$c2, $t2, $m2, $n2] = $T['a'];
printf("| **Total** | %d/%d (**%s**) | %d/%d (**%s**) | %s | %d/%d (%s) | %d/%d (%s) | %s |\n",
    $c1, $t1, $pct($c1, $t1), $c2, $t2, $pct($c2, $t2), $delta($c1, $t1, $c2, $t2),
    $m1, $n1, $pct($m1, $n1), $m2, $n2, $pct($m2, $n2), $delta($m1, $n1, $m2, $n2));

// Checkout tanpa Database/
$sum = function ($files) {
    $c = $t = 0;
    foreach ($files as $f => $r) {
        if ($r['dir'] === 'Checkout/src' && ! str_contains($f, '/Database/')) {
            $c += $r['cst']; $t += $r['st'];
        }
    }

    return [$c, $t];
};
[$cb, $tb] = $sum($b['files']);
[$ca, $ta] = $sum($a['files']);
printf("\nCheckout/src tanpa Database/: %d/%d (%s) -> %d/%d (%s), %s\n", $cb, $tb, $pct($cb, $tb), $ca, $ta, $pct($ca, $ta), $delta($cb, $tb, $ca, $ta));

if ($pattern) {
    echo "\n| Method | Baseline | Sesudah | Δ |\n|---|---|---|---|\n";
    foreach ($a['methods'] as $m => $r) {
        if (! preg_match($pattern, $m)) {
            continue;
        }
        $o = $b['methods'][$m] ?? ['cst' => 0, 'st' => $r['st']];
        printf("| `%s` | %d/%d (%s) | %d/%d (%s) | %s |\n", $m, $o['cst'], $o['st'], $pct($o['cst'], $o['st']),
            $r['cst'], $r['st'], $pct($r['cst'], $r['st']), $delta($o['cst'], $o['st'], $r['cst'], $r['st']));
    }
}
