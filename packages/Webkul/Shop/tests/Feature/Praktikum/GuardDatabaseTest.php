<?php

/**
 * Test penjaga (guard): memastikan suite berjalan di database test,
 * bukan di database utama. Tidak menulis data apa pun.
 *
 * Jalankan file ini PERTAMA sebelum suite lain:
 *   vendor/bin/pest packages/Webkul/Shop/tests/Feature/Praktikum/GuardDatabaseTest.php
 */

use Illuminate\Support\Facades\DB;

it('GUARD-01: koneksi database aktif adalah bagisto_test', function () {
    expect(app()->environment())->toBe('testing');

    expect(config('database.default'))->toBe('mysql');

    expect(DB::connection()->getDatabaseName())->toBe('bagisto_test');

    expect(DB::selectOne('select database() as db')->db)->toBe('bagisto_test');
});
