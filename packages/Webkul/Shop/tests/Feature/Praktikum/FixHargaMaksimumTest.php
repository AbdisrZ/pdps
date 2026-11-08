<?php

/**
 * FASE 5 - Regresi DEF-01: harga di atas kapasitas kolom decimal(12,4)
 * (99.999.999,9999) diterima validasi lalu dipotong diam-diam oleh MySQL
 * (strict=false, config/database.php:58).
 *
 * Kebutuhan REQ-PR-03: nilai di luar kapasitas penyimpanan harus DITOLAK
 * dengan pesan validasi; nilai yang diterima tersimpan persis.
 *
 * Integration test lewat PUT admin/catalog/products/edit/{id} (ProductForm).
 */

use Illuminate\Support\Facades\DB;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;
use Webkul\User\Models\Admin;

uses(PraktikumHelpers::class);

function fx2Update($test, $product, array $override)
{
    $test->actingAs(Admin::factory()->create(), 'admin');

    return $test->putJson(route('admin.catalog.products.update', $product->id), array_merge([
        'sku' => $product->sku,
        'url_key' => $product->url_key,
        'name' => 'Produk Uji Batas Harga',
        'short_description' => 'Deskripsi singkat',
        'description' => 'Deskripsi panjang',
        'price' => '100',
        'weight' => '1',
        'channel' => core()->getCurrentChannelCode(),
        'locale' => app()->getLocale(),
    ], $override));
}

function fx2Stored(int $productId, string $code): ?string
{
    $v = DB::table('product_attribute_values as v')
        ->join('attributes as a', 'a.id', '=', 'v.attribute_id')
        ->where('v.product_id', $productId)
        ->where('a.code', $code)
        ->value('v.float_value');

    return $v === null ? null : (string) $v;
}

function fx2Errors($response): ?array
{
    $json = json_decode((string) $response->baseResponse->getContent(), true);

    return is_array($json) ? ($json['errors'] ?? null) : null;
}

dataset('fx2_ditolak', [
    'FX2-01 price 100000000 (kapasitas + 0,0001)' => ['FX2-01', ['price' => '100000000'], 'price'],
    'FX2-03 cost 100000000' => ['FX2-03', ['cost' => '100000000'], 'cost'],
    'FX2-04 price 13 digit 1000000000000' => ['FX2-04', ['price' => '1000000000000'], 'price'],
]);

it('nilai harga di atas kapasitas ditolak 422 dan nilai lama tidak berubah', function (string $id, array $override, string $field) {
    $p = $this->makeProduct(10);
    $before = fx2Stored($p->id, $field);

    $r = fx2Update($this, $p, $override);
    $after = fx2Stored($p->id, $field);
    $this->record($id, $r, ['input' => $override, 'sebelum' => $before, 'sesudah' => $after, 'errors' => fx2Errors($r)]);

    $r->assertUnprocessable()->assertJsonValidationErrorFor($field);
    expect($after)->toBe($before);
})->with('fx2_ditolak');

dataset('fx2_diterima', [
    'FX2-02 price tepat kapasitas 99999999.9999' => ['FX2-02', ['price' => '99999999.9999'], 'price', '99999999.9999'],
    'FX2-05 price normal 150000' => ['FX2-05', ['price' => '150000'], 'price', '150000.0000'],
    'FX2-06 price terkecil 0.0001' => ['FX2-06', ['price' => '0.0001'], 'price', '0.0001'],
    'FX2-07 cost tepat kapasitas 99999999.9999' => ['FX2-07', ['cost' => '99999999.9999'], 'cost', '99999999.9999'],
]);

it('nilai harga di dalam kapasitas tetap diterima dan tersimpan persis', function (string $id, array $override, string $field, string $expected) {
    $p = $this->makeProduct(10);

    $r = fx2Update($this, $p, $override);
    $stored = fx2Stored($p->id, $field);
    $this->record($id, $r, ['input' => $override, 'tersimpan' => $stored, 'errors' => fx2Errors($r)]);

    expect($r->isRedirect())->toBeTrue();
    expect($stored)->toBe($expected);
})->with('fx2_diterima');
