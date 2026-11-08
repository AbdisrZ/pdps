<?php
/**
 * Tampilkan jumlah eksekusi (count) per baris dari clover.xml.
 * Usage: php clover_lines.php <clover.xml> "<akhiran path file>" <baris,baris,a-b,...>
 */
[$_, $clover, $suffix, $spec] = $argv;

$want = [];
foreach (explode(',', $spec) as $p) {
    if (str_contains($p, '-')) {
        [$a, $b] = explode('-', $p);
        $want = array_merge($want, range((int) $a, (int) $b));
    } else {
        $want[] = (int) $p;
    }
}

$xml = simplexml_load_file($clover);

foreach ($xml->xpath('//file') as $f) {
    $path = str_replace(chr(92), '/', (string) $f['name']);

    if (! str_ends_with($path, $suffix)) {
        continue;
    }

    $lines = [];
    foreach ($f->line as $l) {
        $lines[(int) $l['num']] = [(string) $l['type'], (int) $l['count']];
    }

    foreach ($want as $n) {
        $info = isset($lines[$n]) ? $lines[$n][0].' count='.$lines[$n][1] : '(bukan baris executable)';
        echo basename($suffix).':'.$n.' => '.$info.PHP_EOL;
    }
}
