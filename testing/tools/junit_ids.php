<?php
/**
 * Ringkas hasil JUnit Pest per ID kasus (mis. "ST-04", "CO-02").
 * Usage: php junit_ids.php <run1.xml> [<run2.xml> ...]
 * Output: TSV  ID  status_run1  status_run2 ...  waktu_run1(s)  nama_test
 */
$runs = array_slice($argv, 1);
$table = [];

foreach ($runs as $i => $file) {
    $xml = simplexml_load_file($file);

    foreach ($xml->xpath('//testcase') as $tc) {
        $name = (string) $tc['name'];

        if (! preg_match('/\b([A-Z]{2,5}-\d{2})\b/', $name, $m)) {
            continue;
        }

        $id = $m[1];
        $status = isset($tc->failure) ? 'FAIL' : (isset($tc->error) ? 'ERROR' : (isset($tc->skipped) ? 'SKIP' : 'PASS'));

        $table[$id]['name'] = $name;
        $table[$id]['status'][$i] = $status;
        $table[$id]['time'][$i] = round((float) $tc['time'], 2);
    }
}

ksort($table, SORT_NATURAL);

$identical = true;

foreach ($table as $id => $row) {
    $statuses = $row['status'];

    if (count(array_unique($statuses)) > 1) {
        $identical = false;
    }

    echo $id."\t".implode("\t", $statuses)."\t".$row['time'][0]."\t".$row['name'].PHP_EOL;
}

$fail = count(array_filter($table, fn ($r) => in_array('FAIL', $r['status'], true) || in_array('ERROR', $r['status'], true)));

fwrite(STDERR, sprintf("kasus=%d gagal=%d status_identik_antar_run=%s\n", count($table), $fail, $identical ? 'YA' : 'TIDAK'));
