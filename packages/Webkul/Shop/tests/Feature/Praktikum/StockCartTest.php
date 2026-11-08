<?php

/**
 * Praktikum - Inventory/stok (ST-*) dan Cart (CT-*). Lihat testing/04-blackbox.md §2-§3.
 * Expected diturunkan dari kebutuhan REQ-ST-*, REQ-CT-* (testing/03-test-plan.md §4).
 * Integration test: menyentuh DB bagisto_test (rollback otomatis per test).
 * Nilai aktual tiap kasus: evidence/F4-actual/<ID>.json
 */

use Illuminate\Support\Facades\DB;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\Wishlist;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;

uses(PraktikumHelpers::class);

const PRK_STOK = 5;

function prkItemQty(int $productId): ?int
{
    return CartItem::query()->where('product_id', $productId)->value('quantity');
}

function prkItemOf(int $productId): CartItem
{
    return CartItem::query()->where('product_id', $productId)->firstOrFail();
}

/*
|--------------------------------------------------------------------------
| §2 Stok - batas Simple::haveSufficientQuantity (Simple.php:158)
|--------------------------------------------------------------------------
*/

it('ST-01 [EP-V] qty di tengah rentang (2 dari stok 5) diterima', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, 2);
    $this->record('ST-01', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(2);
});

it('ST-02 [BVA] qty = stok-1 (4) diterima', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, PRK_STOK - 1);
    $this->record('ST-02', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(PRK_STOK - 1);
});

it('ST-03 [BVA] qty = stok (5) diterima', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, PRK_STOK);
    $this->record('ST-03', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(PRK_STOK);
});

it('ST-04 [BVA] qty = stok+1 (6) ditolak 400 inventory-warning, tidak ada item', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, PRK_STOK + 1);
    $this->record('ST-04', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertStatus(400)
        ->assertJsonPath('message', trans('product::app.checkout.cart.inventory-warning'));
    expect(prkItemQty($p->id))->toBeNull();
});

it('ST-05 [BVA kumulatif] sudah 5 di cart lalu tambah 1 ditolak, qty tetap 5', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, PRK_STOK)->assertOk();

    $r = $this->addToCart($p, 1);
    $this->record('ST-05', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertStatus(400);
    expect(prkItemQty($p->id))->toBe(PRK_STOK);
});

it('ST-06 [BVA kumulatif] sudah 4 di cart lalu tambah 1 diterima, qty 5', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, PRK_STOK - 1)->assertOk();

    $r = $this->addToCart($p, 1);
    $this->record('ST-06', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(PRK_STOK);
});

it('ST-07 [BVA update] PUT qty menjadi stok (5) diterima', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 1)->assertOk();
    $item = prkItemOf($p->id);

    $r = $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$item->id => PRK_STOK]]);
    $this->record('ST-07', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk()->assertJsonPath('message', trans('shop::app.checkout.cart.index.quantity-update'));
    expect(prkItemQty($p->id))->toBe(PRK_STOK);
});

it('ST-08 [BVA update] PUT qty menjadi stok+1 (6) ditolak dengan pesan stok, qty tetap lama', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 2)->assertOk();
    $item = prkItemOf($p->id);

    $r = $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$item->id => PRK_STOK + 1]]);
    $this->record('ST-08', $r, ['qty_item' => prkItemQty($p->id)]);

    // REQ-ST-01: stok tidak boleh terlampaui; qty lama dipertahankan dan ada pesan stok.
    // Catatan: update() membungkus pesan di data.message (CartController.php:160-162);
    // konsistensi kode status HTTP diuji terpisah di CT-11..CT-14 (D3).
    expect(prkItemQty($p->id))->toBe(2);
    $r->assertJsonPath('data.message', trans('shop::app.checkout.cart.inventory-warning'));
});

it('ST-09 [EP-I] stok 0 ditolak 400', function () {
    $p = $this->makeProduct(0);

    $r = $this->addToCart($p, 1);
    $this->record('ST-09', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertStatus(400);
    expect(prkItemQty($p->id))->toBeNull();
});

it('ST-10 [EP-V] back-order aktif: qty stok+1 diterima (REQ-ST-02)', function () {
    $this->setConfig('catalog.inventory.stock_options.back_orders', '1');
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, PRK_STOK + 1);
    $this->record('ST-10', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(PRK_STOK + 1);
});

it('ST-11 [EP-V] manage_stock = 0: qty 50 diterima (Simple.php:154-156)', function () {
    $p = $this->makeProduct(PRK_STOK, ['manage_stock' => ['boolean_value' => false, 'channel' => 'default']]);

    $r = $this->addToCart($p, 50);
    $this->record('ST-11', $r, ['manage_stock' => (bool) $p->manage_stock, 'qty_item' => prkItemQty($p->id)]);

    expect((bool) $p->manage_stock)->toBeFalse();
    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(50);
});

it('ST-12 [BVA] stok terpesan mengurangi ketersediaan: sumber 5 - terpesan 3 = 2', function () {
    $p = $this->makeProduct(PRK_STOK);

    DB::table('product_ordered_inventories')->insert([
        'product_id' => $p->id,
        'channel_id' => core()->getCurrentChannel()->id,
        'qty' => 3,
    ]);
    $this->reindexInventory($p);

    $r3 = $this->addToCart($p, 3);
    $r2 = $this->addToCart($p, 2);
    $this->record('ST-12', $r2, ['status_qty3' => $r3->status(), 'qty_item' => prkItemQty($p->id)]);

    $r3->assertStatus(400);
    $r2->assertOk();
    expect(prkItemQty($p->id))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| §3 Cart - validasi input (CartController.php:51-55) dan konsistensi respons
|--------------------------------------------------------------------------
*/

it('CT-01 [EP-I] product_id kosong ditolak 422', function () {
    $r = $this->postJson(route('shop.api.checkout.cart.store'), ['quantity' => 1]);
    $this->record('CT-01', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('product_id');
});

it('CT-02 [EP-I] product_id tidak ada ditolak 422', function () {
    $r = $this->postJson(route('shop.api.checkout.cart.store'), ['product_id' => 999999999, 'quantity' => 1]);
    $this->record('CT-02', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('product_id');
});

it('CT-03 [EP-I] product_id bukan integer ditolak 422', function () {
    $r = $this->postJson(route('shop.api.checkout.cart.store'), ['product_id' => 'abc', 'quantity' => 1]);
    $this->record('CT-03', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('product_id');
});

it('CT-04 [BVA] quantity = 0 (di bawah min:1) ditolak 422', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, 0);
    $this->record('CT-04', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('quantity');
});

it('CT-05 [BVA] quantity = 1 (batas min) diterima', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, 1);
    $this->record('CT-05', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(1);
});

it('CT-06 [EP-I] quantity negatif ditolak 422', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, -1);
    $this->record('CT-06', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('quantity');
});

it('CT-07 [EP-I] quantity desimal 1.5 ditolak 422', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->postJson(route('shop.api.checkout.cart.store'), ['product_id' => $p->id, 'quantity' => 1.5]);
    $this->record('CT-07', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('quantity');
});

it('CT-08 [EP-V][H14] quantity tidak dikirim -> 200, qty default 1 (REQ-CT-01)', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->postJson(route('shop.api.checkout.cart.store'), ['product_id' => $p->id]);
    $this->record('CT-08', $r, ['qty_item' => prkItemQty($p->id)]);

    $r->assertOk();
    expect(prkItemQty($p->id))->toBe(1);
});

it('CT-09 [EP-I] produk nonaktif ditolak 400 inactive-add', function () {
    $p = $this->makeProduct(PRK_STOK, ['status' => ['boolean_value' => false, 'channel' => 'default']]);

    $r = $this->addToCart($p, 1);
    $this->record('CT-09', $r);

    $r->assertStatus(400)->assertJsonPath('message', trans('shop::app.checkout.cart.inactive-add'));
});

it('CT-10 [EP-I] is_buy_now di luar {0,1} ditolak 422', function () {
    $p = $this->makeProduct(PRK_STOK);

    $r = $this->addToCart($p, 1, ['is_buy_now' => 2]);
    $this->record('CT-10', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('is_buy_now');
});

it('CT-11 [BVA][H10][D3] PUT qty = 0 -> 4xx dan item TETAP ada (REQ-CT-02/03)', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 2)->assertOk();
    $item = prkItemOf($p->id);

    $r = $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$item->id => 0]]);
    $this->record('CT-11', $r, ['qty_item_sesudah' => prkItemQty($p->id)]);

    expect(prkItemQty($p->id))->toBe(2);
    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-12 [EP-I][H10][D3] PUT qty negatif -> 4xx dan item TETAP ada', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 2)->assertOk();
    $item = prkItemOf($p->id);

    $r = $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$item->id => -1]]);
    $this->record('CT-12', $r, ['qty_item_sesudah' => prkItemQty($p->id)]);

    expect(prkItemQty($p->id))->toBe(2);
    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-13 [EP-I][H11] PUT qty non-angka -> 4xx dan qty tidak berubah', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 2)->assertOk();
    $item = prkItemOf($p->id);

    $r = $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$item->id => 'abc']]);
    $this->record('CT-13', $r, ['qty_item_sesudah' => prkItemQty($p->id)]);

    expect(prkItemQty($p->id))->toBe(2);
    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-14 [EP-I][D3] PUT tanpa key qty -> 4xx (bukan 200)', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 1)->assertOk();

    $r = $this->putJson(route('shop.api.checkout.cart.update'), []);
    $this->record('CT-14', $r);

    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-15 [EP-I][D2] DELETE selected tanpa ids -> 422 (bukan 500)', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 1)->assertOk();

    $r = $this->deleteJson(route('shop.api.checkout.cart.destroy_selected'), []);
    $this->record('CT-15', $r);

    $r->assertUnprocessable();
});

it('CT-16 [EP-I kontrol] DELETE tanpa cart_item_id -> 422', function () {
    $r = $this->deleteJson(route('shop.api.checkout.cart.destroy'), []);
    $this->record('CT-16', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('cart_item_id');
});

it('CT-17 [EP-I][D1] estimate-shipping-methods tanpa cart -> 4xx (bukan 500)', function () {
    $this->actAsGuest();

    $r = $this->postJson(route('shop.api.checkout.cart.estimate_shipping'), [
        'country' => 'ID',
        'state' => 'Jawa Barat',
        'postcode' => '40115',
    ]);
    $this->record('CT-17', $r);

    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-18 [EP-I] estimate-shipping-methods tanpa country -> 422', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 1)->assertOk();

    $r = $this->postJson(route('shop.api.checkout.cart.estimate_shipping'), [
        'state' => 'Jawa Barat',
        'postcode' => '40115',
    ]);
    $this->record('CT-18', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('country');
});

it('CT-19 [EP-I][H21] move-to-wishlist oleh guest ditolak, tidak ada wishlist tanpa pemilik', function () {
    $p = $this->makeProduct(PRK_STOK);
    $this->addToCart($p, 1)->assertOk();
    $item = prkItemOf($p->id);

    $r = $this->postJson(route('shop.api.checkout.cart.move_to_wishlist'), [
        'ids' => [$item->id],
        'qty' => [1],
    ]);
    $this->record('CT-19', $r, [
        'wishlist_tanpa_customer' => Wishlist::query()->whereNull('customer_id')->count(),
        'item_masih_di_cart' => CartItem::query()->whereKey($item->id)->exists(),
    ]);

    expect(Wishlist::query()->whereNull('customer_id')->count())->toBe(0);
    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-20 [EP-I][D5] pasang kupon tanpa cart -> 4xx (bukan 500)', function () {
    $this->actAsGuest();

    $r = $this->postJson(route('shop.api.checkout.cart.coupon.apply'), ['code' => 'TIDAKADA']);
    $this->record('CT-20', $r);

    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('CT-21 [EP-I][D5] hapus kupon tanpa cart -> tanpa error server (bukan 500)', function () {
    $this->actAsGuest();

    $r = $this->deleteJson(route('shop.api.checkout.cart.coupon.remove'));
    $this->record('CT-21', $r);

    expect($r->status())->toBeLessThan(500);
});
