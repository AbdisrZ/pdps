<?php

/**
 * Praktikum - Validasi harga produk di Admin (PR-*). Lihat testing/04-blackbox.md §7.
 * Kebutuhan: REQ-PR-01..03. Aturan: Admin/src/Http/Requests/ProductForm.php:84-86, :116-137;
 * Core/src/Rules/Decimal.php:15. Kapasitas kolom decimal(12,4) = 99.999.999,9999.
 * Payload mengikuti test bawaan Admin/tests/Feature/Catalog/Products/Types/SimpleTest.php:114-131.
 * Nilai aktual: evidence/F4-actual/<ID>.json
 */

use Illuminate\Support\Facades\DB;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;
use Webkul\User\Models\Admin;

uses(PraktikumHelpers::class);

function prkUpdateProduct($test, $product, array $override)
{
    $test->actingAs(Admin::factory()->create(), 'admin');

    return $test->putJson(route('admin.catalog.products.update', $product->id), array_merge([
        'sku' => $product->sku,
        'url_key' => $product->url_key,
        'name' => 'Produk Uji Harga',
        'short_description' => 'Deskripsi singkat',
        'description' => 'Deskripsi panjang produk uji',
        'price' => '100',
        'weight' => '1',
        'channel' => core()->getCurrentChannelCode(),
        'locale' => app()->getLocale(),
    ], $override));
}

function prkStoredAttr(int $productId, string $code): ?string
{
    $value = DB::table('product_attribute_values as v')
        ->join('attributes as a', 'a.id', '=', 'v.attribute_id')
        ->where('v.product_id', $productId)
        ->where('a.code', $code)
        ->value('v.float_value');

    return $value === null ? null : (string) $value;
}

function prkErrors($response): ?array
{
    $json = json_decode((string) $response->baseResponse->getContent(), true);

    return is_array($json) ? ($json['errors'] ?? null) : null;
}

function prkIsSaved($response): bool
{
    return $response->isRedirect() || $response->status() === 200;
}

dataset('prk_harga_valid', [
    'PR-01 [EP-V] bilangan bulat 150000' => ['PR-01', ['price' => '150000'], 'price', '150000.0000'],
    'PR-02 [BVA][H17] batas bawah 0' => ['PR-02', ['price' => '0'], 'price', '0.0000'],
    'PR-04 [BVA] 4 desimal' => ['PR-04', ['price' => '1.1234'], 'price', '1.1234'],
    'PR-08 [BVA][H18] tepat kapasitas 99999999.9999' => ['PR-08', ['price' => '99999999.9999'], 'price', '99999999.9999'],
    'PR-11 [BVA] special = price - 0.0001' => ['PR-11', ['price' => '100', 'special_price' => '99.9999'], 'special_price', '99.9999'],
]);

it('harga valid diterima dan tersimpan persis', function (string $id, array $override, string $field, string $expected) {
    $p = $this->makeProduct(10);

    $r = prkUpdateProduct($this, $p, $override);
    $stored = prkStoredAttr($p->id, $field);
    $this->record($id, $r, ['input' => $override, 'tersimpan' => $stored, 'errors' => prkErrors($r)]);

    expect(prkIsSaved($r))->toBeTrue();
    expect($stored)->toBe($expected);
})->with('prk_harga_valid');

dataset('prk_harga_invalid', [
    'PR-03 [EP-I] negatif' => ['PR-03', ['price' => '-1'], 'price'],
    'PR-05 [BVA] 5 desimal' => ['PR-05', ['price' => '1.12345'], 'price'],
    'PR-06 [EP-I] non-angka' => ['PR-06', ['price' => 'abc'], 'price'],
    'PR-07 [EP-I] kosong' => ['PR-07', ['price' => ''], 'price'],
    'PR-10 [BVA] special = price' => ['PR-10', ['price' => '100', 'special_price' => '100'], 'special_price'],
    'PR-12 [EP-I] special > price' => ['PR-12', ['price' => '100', 'special_price' => '101'], 'special_price'],
    'PR-13 [EP-I] special_price_to < from' => ['PR-13', ['special_price' => '90', 'special_price_from' => '2026-12-10', 'special_price_to' => '2026-12-09'], 'special_price_to'],
    'PR-14 [EP-I] cost negatif' => ['PR-14', ['cost' => '-5'], 'cost'],
]);

it('harga invalid ditolak 422 dan nilai lama tidak berubah', function (string $id, array $override, string $field) {
    $p = $this->makeProduct(10);
    $priceBefore = prkStoredAttr($p->id, 'price');

    $r = prkUpdateProduct($this, $p, $override);
    $this->record($id, $r, ['input' => $override, 'price_sebelum' => $priceBefore, 'price_sesudah' => prkStoredAttr($p->id, 'price')]);

    $r->assertUnprocessable()->assertJsonValidationErrorFor($field);
    expect(prkStoredAttr($p->id, 'price'))->toBe($priceBefore);
})->with('prk_harga_invalid');

it('PR-09 [BVA][H18] harga 100000000 (kapasitas + 0.0001) ditolak 422 atau tersimpan persis, tidak boleh terpotong', function () {
    $p = $this->makeProduct(10);

    $r = prkUpdateProduct($this, $p, ['price' => '100000000']);
    $stored = prkStoredAttr($p->id, 'price');
    $flat = DB::table('product_flat')->where('product_id', $p->id)->value('price');
    $this->record('PR-09', $r, [
        'input' => '100000000',
        'tersimpan_attribute_values' => $stored,
        'tersimpan_product_flat' => $flat === null ? null : (string) $flat,
        'errors' => prkErrors($r),
    ]);

    $ditolak = $r->status() === 422;

    expect($ditolak || $stored === '100000000.0000')->toBeTrue();
});
