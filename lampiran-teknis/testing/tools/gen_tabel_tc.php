<?php
/**
 * Buat tabel daftar test case (Markdown) untuk TB.md.
 * Sumber: 04-blackbox.md (skenario & expected) + status JUnit run awal dan run akhir.
 * Usage: php gen_tabel_tc.php <04-blackbox.md> <status.tsv>
 *   status.tsv = output junit_ids.php <F4-run1-junit.xml> <F5-final-praktikum-junit.xml>
 */
[$_, $blackbox, $tsv] = $argv;

$modul = [
    'AZ' => 'Hak akses', 'ST' => 'Stok', 'CT' => 'Keranjang', 'CO' => 'Alamat checkout',
    'SH' => 'Ongkir', 'OR' => 'Pesanan', 'MO' => 'Minimum order', 'AU' => 'Autentikasi', 'PR' => 'Harga (admin)',
];
$defect = [
    'PR-09' => 'DEF-01', 'CT-11' => 'DEF-02', 'CT-12' => 'DEF-02', 'MO-04' => 'DEF-03', 'MO-05' => 'DEF-04',
    'PR-02' => 'DEF-05', 'CT-08' => 'DEF-06', 'CT-13' => 'DEF-07', 'CT-15' => 'DEF-07', 'CT-17' => 'DEF-07',
    'CT-19' => 'DEF-07', 'CT-21' => 'DEF-07', 'CO-19' => 'DEF-07', 'CO-21' => 'DEF-07', 'CT-14' => 'DEF-08',
    'OR-02' => 'DEF-09', 'AZ-02' => 'DEF-10', 'CO-02' => 'DEF-11', 'CO-18' => 'DEF-12', 'AZ-13' => 'DEF-13',
    'AU-16' => 'DEF-14', 'AU-15' => 'DEF-15',
];
$fixed = ['MO-04' => 'v1.0.1', 'PR-09' => 'v1.0.2'];

$clean = function (string $s, int $max = 150): string {
    $s = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $s);
    $s = str_replace('**', '', $s);
    $s = preg_replace('/\*([^*]+)\*/', '$1', $s);
    $s = preg_replace('/\s*\((?:REQ|`)[^)]*\)\s*$/', '', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));

    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
};

$rows = [];
$header = null;
foreach (file($blackbox) as $line) {
    if (! str_starts_with($line, '|')) { $header = null; continue; }
    $c = array_map('trim', explode('|', trim($line, " \n\r|")));
    if ($header === null) { $header = $c; continue; }
    if (preg_match('/^-+$/', $c[0] ?? '')) { continue; }
    if (! preg_match('/^([A-Z]{2})-\d{2}$/', $c[0] ?? '', $m) || ! isset($modul[$m[1]])) { continue; }
    $iExp = array_search('Expected', $header, true);
    if ($iExp === false) { continue; }
    $parts = [];
    for ($i = 2; $i < $iExp; $i++) {
        if (in_array($header[$i], ['Rujukan'], true)) { continue; }
        if ($c[$i] !== '') { $parts[] = $c[$i]; }
    }
    if ($c[0] === 'SH-05') { $c[$iExp] = '200 (tarif flat rate memang tersedia tanpa alamat); pesanan tanpa alamat tetap ditolak. Hasil yang diharapkan dikoreksi saat eksekusi'; }
    $rows[$c[0]] = [
        'modul' => $modul[$m[1]],
        'teknik' => preg_replace('/\s*\(.*$|\[.*$/', '', $c[1]),
        'skenario' => $clean(implode(' — ', $parts), 120),
        'expected' => $clean($c[$iExp], 130),
    ];
}

$status = [];
foreach (file($tsv) as $l) {
    $p = explode("\t", trim($l));
    if (count($p) >= 3) { $status[$p[0]] = [$p[1], $p[2]]; }
}

$order = ['AZ', 'ST', 'CT', 'CO', 'SH', 'OR', 'MO', 'AU', 'PR'];
uksort($rows, function ($a, $b) use ($order) {
    $pa = array_search(substr($a, 0, 2), $order); $pb = array_search(substr($b, 0, 2), $order);
    return $pa <=> $pb ?: strnatcmp($a, $b);
});

echo "| ID | Modul | Skenario / input | Teknik | Hasil yang diharapkan | Status | Defect |\n|---|---|---|---|---|---|---|\n";
$n = 0;
foreach ($rows as $id => $r) {
    [$awal, $akhir] = $status[$id] ?? ['?', '?'];
    $st = $awal === 'PASS' ? 'PASS' : 'FAIL';
    if (isset($fixed[$id])) { $st = "FAIL → **PASS** ({$fixed[$id]})"; }
    elseif ($awal !== $akhir) { $st = "$awal → $akhir"; }
    $d = $defect[$id] ?? '–';
    $esc = fn ($s) => str_replace('|', '\\|', $s);
    echo "| $id | {$r['modul']} | {$esc($r['skenario'])} | {$r['teknik']} | {$esc($r['expected'])} | $st | $d |\n";
    $n++;
}
fwrite(STDERR, "baris=$n\n");
