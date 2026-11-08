<?php

/**
 * Praktikum - Checkout: alamat (CO-*), shipping (SH-*), order (OR-*), minimum order (MO-*).
 * Lihat testing/04-blackbox.md §4-§5. Kebutuhan: REQ-CO-01..05, REQ-CT-03.
 * CO/SH/OR/MO-01..03 = integration (HTTP + DB test). MO-04/MO-05 = unit (Cart::getOrderAmount
 * dengan model cart in-memory, tanpa HTTP).
 * Nilai aktual: evidence/F4-actual/<ID>.json
 */

use Illuminate\Support\Facades\DB;
use Webkul\Checkout\Models\Cart as CartModel;
use Webkul\Checkout\Models\CartAddress;
use Webkul\Sales\Models\Order;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;

uses(PraktikumHelpers::class);

function prkGuestWithCart($test, int $qty = 1, array $attr = []): \Webkul\Product\Models\Product
{
    $test->actAsGuest();
    $p = $test->makeProduct(20, $attr);
    $test->addToCart($p, $qty)->assertOk();

    return $p;
}

function prkBillingRow(): ?CartAddress
{
    return CartAddress::query()->where('address_type', CartAddress::ADDRESS_TYPE_BILLING)->latest('id')->first();
}

/*
|--------------------------------------------------------------------------
| §4 Alamat - CartAddressRequest.php:50-58, PhoneNumber.php:23, PostCode.php:15
|--------------------------------------------------------------------------
*/

it('CO-01 [EP-V] alamat valid lengkap -> 200, ada shipping methods', function () {
    prkGuestWithCart($this);

    $r = $this->postAddress();
    $this->record('CO-01', $r);

    $r->assertOk()->assertJsonPath('redirect', false);
    expect($r->json('data.shippingMethods'))->toHaveKey('flatrate');
});

dataset('prk_alamat_invalid', [
    'CO-02 [EP-I][D7] email tanpa format email' => ['CO-02', ['email' => 'bukan-email'], 'billing.email'],
    'CO-03 [EP-I] email kosong' => ['CO-03', ['email' => ''], 'billing.email'],
    'CO-04 [EP-I] first_name kosong' => ['CO-04', ['first_name' => ''], 'billing.first_name'],
    'CO-05 [EP-I] address array kosong' => ['CO-05', ['address' => []], 'billing.address'],
    'CO-06 [EP-I] address string bukan array' => ['CO-06', ['address' => 'Jl. Uji'], 'billing.address'],
    'CO-07 [EP-I] country kosong' => ['CO-07', ['country' => ''], 'billing.country'],
    'CO-09 [BVA] phone "+" (0 digit)' => ['CO-09', ['phone' => '+'], 'billing.phone'],
    'CO-11 [EP-I] phone dengan tanda hubung' => ['CO-11', ['phone' => '0812-3456'], 'billing.phone'],
    'CO-12 [EP-I] phone dengan spasi' => ['CO-12', ['phone' => '0812 3456'], 'billing.phone'],
    'CO-13 [BVA] postcode 1 karakter' => ['CO-13', ['postcode' => '1'], 'billing.postcode'],
    'CO-15 [EP-I] postcode diakhiri tanda hubung' => ['CO-15', ['postcode' => '1-'], 'billing.postcode'],
]);

it('menolak alamat invalid dengan 422', function (string $id, array $override, string $field) {
    prkGuestWithCart($this);

    $r = $this->postAddress($override);
    $this->record($id, $r, ['input' => $override]);

    $r->assertUnprocessable()->assertJsonValidationErrorFor($field);
})->with('prk_alamat_invalid');

dataset('prk_alamat_valid_batas', [
    'CO-08 [BVA] phone "1" (1 digit)' => ['CO-08', ['phone' => '1']],
    'CO-10 [EP-V] phone +62' => ['CO-10', ['phone' => '+6281234567890']],
    'CO-14 [BVA] postcode 2 karakter' => ['CO-14', ['postcode' => '12']],
    'CO-16 [EP-V] postcode dengan spasi di tengah' => ['CO-16', ['postcode' => '12 34']],
]);

it('menerima alamat valid di batas dengan 200', function (string $id, array $override) {
    prkGuestWithCart($this);

    $r = $this->postAddress($override);
    $this->record($id, $r, ['input' => $override]);

    $r->assertOk()->assertJsonPath('redirect', false);
})->with('prk_alamat_valid_batas');

it('CO-17 [BVA][H23] first_name 255 karakter -> 200, tersimpan utuh', function () {
    prkGuestWithCart($this);
    $name = str_repeat('a', 255);

    $r = $this->postAddress(['first_name' => $name]);
    $this->record('CO-17', $r, ['panjang_tersimpan' => mb_strlen((string) prkBillingRow()?->first_name)]);

    $r->assertOk();
    expect(prkBillingRow()->first_name)->toBe($name);
});

it('CO-18 [BVA][H23] first_name 256 karakter -> 422 atau tersimpan utuh (tidak terpotong diam-diam)', function () {
    prkGuestWithCart($this);
    $name = str_repeat('b', 256);

    $r = $this->postAddress(['first_name' => $name]);
    $stored = prkBillingRow()?->first_name;
    $this->record('CO-18', $r, ['panjang_dikirim' => 256, 'panjang_tersimpan' => $stored === null ? null : mb_strlen($stored)]);

    $ditolak = $r->status() === 422;

    expect($ditolak || $stored === $name)->toBeTrue();
});

it('CO-19 [EP-I][D8] tanpa key billing, hanya shipping -> 422 (bukan 500)', function () {
    prkGuestWithCart($this);

    $r = $this->postJson(route('shop.checkout.onepage.addresses.store'), [
        'shipping' => $this->billing(['use_for_shipping' => 0]),
    ]);
    $this->record('CO-19', $r);

    $r->assertUnprocessable();
});

it('CO-20 [EP-I kontrol] use_for_shipping=0 tanpa shipping -> 422', function () {
    prkGuestWithCart($this);

    $r = $this->postAddress(['use_for_shipping' => 0]);
    $this->record('CO-20', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('shipping.first_name');
});

it('CO-21 [EP-I][H9] guest tanpa cart mengirim alamat -> 4xx/redirect (bukan 500)', function () {
    $this->actAsGuest();

    $r = $this->postAddress();
    $this->record('CO-21', $r);

    expect($r->status())->toBeLessThan(500);
});

/*
|--------------------------------------------------------------------------
| §5 Shipping, order
|--------------------------------------------------------------------------
*/

it('SH-01 [EP-I] shipping_method kosong -> 422', function () {
    prkGuestWithCart($this);
    $this->postAddress()->assertOk();

    $r = $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), []);
    $this->record('SH-01', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('shipping_method');
});

it('SH-02 [EP-V] flatrate_flatrate -> 200 dengan payment methods', function () {
    prkGuestWithCart($this);
    $this->postAddress()->assertOk();

    $r = $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'flatrate_flatrate']);
    $this->record('SH-02', $r);

    $r->assertOk();
    expect(collect($r->json('payment_methods'))->pluck('method'))->toContain('cashondelivery');
});

it('SH-03 [EP-V] free_free -> 200', function () {
    prkGuestWithCart($this);
    $this->postAddress()->assertOk();

    $r = $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free']);
    $this->record('SH-03', $r);

    $r->assertOk();
});

it('SH-04 [EP-I] kode shipping tidak dikenal -> 403', function () {
    prkGuestWithCart($this);
    $this->postAddress()->assertOk();

    $r = $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'kurir_palsu']);
    $this->record('SH-04', $r);

    $r->assertForbidden();
});

it('SH-05 [EP-V, expected dikoreksi] shipping sebelum alamat: rate flatrate tersedia (200), tetapi order tanpa alamat ditolak', function () {
    // Koreksi expected (lihat 06-hasil-eksekusi.md): expected awal "403" adalah asumsi tentang kode,
    // bukan turunan REQ-CO-03. REQ-CO-03 hanya mensyaratkan kode = rate yang tersedia; urutan
    // alamat->order dijaga REQ-CO-04 (OnepageController.php:230-236).
    prkGuestWithCart($this);

    $r = $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'flatrate_flatrate']);
    $this->postJson(route('shop.checkout.onepage.payment_methods.store'), ['payment' => ['method' => 'cashondelivery']]);
    $before = Order::query()->count();
    $order = $this->postJson(route('shop.checkout.onepage.orders.store'));
    $this->record('SH-05', $r, ['status_order_tanpa_alamat' => $order->status(), 'pesan_order' => $order->json('message'), 'order_bertambah' => Order::query()->count() - $before]);

    $r->assertOk();
    expect(Order::query()->count())->toBe($before);
});

it('SH-06 [BVA] ongkir flat rate per unit: qty 1 -> 10, qty 3 -> 30', function () {
    $hasil = [];

    foreach ([1 => 10, 3 => 30] as $qty => $expected) {
        prkGuestWithCart($this, $qty);
        $this->postAddress()->assertOk();
        $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'flatrate_flatrate'])->assertOk();

        $hasil[$qty] = (float) CartModel::query()->latest('id')->value('base_shipping_amount');
    }

    $this->record('SH-06', null, ['base_shipping_amount_per_qty' => $hasil]);

    expect($hasil[1])->toEqual(10.0);
    expect($hasil[3])->toEqual(30.0);
});

it('OR-01 [EP-V] checkout lengkap membuat order dan menonaktifkan cart', function () {
    $c = $this->makeCustomer();
    $this->actAsCustomer($c);

    $order = $this->placeOrder($this->makeProduct(10), 2);
    $this->record('OR-01', null, ['order_id' => $order->id, 'status' => $order->status, 'cart_aktif' => $this->activeCartOf($c)?->id]);

    expect($order->customer_id)->toBe($c->id);
    expect($order->status)->toBe('pending');
    expect($this->activeCartOf($c))->toBeNull();
});

it('OR-02 [EP-I][H24] order tanpa payment -> tidak dibuat, status 4xx (REQ-CO-04, REQ-CT-03)', function () {
    prkGuestWithCart($this);
    $this->postAddress()->assertOk();
    $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'flatrate_flatrate'])->assertOk();
    $before = Order::query()->count();

    $r = $this->postJson(route('shop.checkout.onepage.orders.store'));
    $this->record('OR-02', $r, ['order_bertambah' => Order::query()->count() - $before]);

    expect(Order::query()->count())->toBe($before);
    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('OR-03 [EP-I] customer suspended tidak dapat membuat order', function () {
    $c = $this->makeCustomer(['is_suspended' => 1]);
    $this->actAsCustomer($c);
    $this->addToCart($this->makeProduct(10), 1)->assertOk();
    $this->postAddress()->assertOk();
    $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'flatrate_flatrate'])->assertOk();
    $this->postJson(route('shop.checkout.onepage.payment_methods.store'), ['payment' => ['method' => 'cashondelivery']])->assertOk();
    $before = Order::query()->count();

    $r = $this->postJson(route('shop.checkout.onepage.orders.store'));
    $this->record('OR-03', $r, ['order_bertambah' => Order::query()->count() - $before]);

    expect(Order::query()->count())->toBe($before);
});

/*
|--------------------------------------------------------------------------
| §5 Minimum order - Cart.php:789-818
|--------------------------------------------------------------------------
*/

dataset('prk_min_order', [
    'MO-01 [BVA] subtotal = min - 0.01 ditolak' => ['MO-01', 99.99, false],
    'MO-02 [BVA] subtotal = min diterima' => ['MO-02', 100.00, true],
    'MO-03 [BVA] subtotal = min + 0.01 diterima' => ['MO-03', 100.01, true],
]);

it('minimum order 100 dibandingkan subtotal secara inklusif', function (string $id, float $price, bool $allowed) {
    $this->setConfig('sales.order_settings.minimum_order.enable', '1');
    $this->setConfig('sales.order_settings.minimum_order.minimum_order_amount', '100');

    $c = $this->makeCustomer();
    $this->actAsCustomer($c);
    $this->addToCart($this->makeProduct(10, ['price' => ['float_value' => $price]]), 1)->assertOk();
    $before = Order::query()->count();

    $this->postAddress();
    $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'free_free']);
    $this->postJson(route('shop.checkout.onepage.payment_methods.store'), ['payment' => ['method' => 'cashondelivery']]);
    $r = $this->postJson(route('shop.checkout.onepage.orders.store'));

    $dibuat = Order::query()->count() > $before;
    $this->record($id, $r, ['harga' => $price, 'order_dibuat' => $dibuat]);

    expect($dibuat)->toBe($allowed);
})->with('prk_min_order');

it('MO-04 [EP][H13] include_discount_amount=1 tidak boleh mengurangi pajak dari jumlah pembanding (unit)', function () {
    $this->setConfig('sales.order_settings.minimum_order.include_discount_amount', '1');

    $cart = new CartModel(['sub_total' => 100, 'tax_total' => 10, 'discount_amount' => 0]);
    cart()->setCart($cart);

    $amount = cart()->getOrderAmount();
    $this->record('MO-04', null, ['sub_total' => 100, 'tax_total' => 10, 'discount' => 0, 'getOrderAmount' => $amount]);

    // Kebutuhan REQ-CO-05: subtotal dikurangi diskon (0) => 100.
    expect($amount)->toEqual(100);
});

it('MO-05 [BVA][H26] minimum 100.50 dan subtotal 100.25 -> minimum TIDAK terpenuhi (unit)', function () {
    $this->setConfig('sales.order_settings.minimum_order.enable', '1');
    $this->setConfig('sales.order_settings.minimum_order.minimum_order_amount', '100.50');

    $cart = new CartModel(['sub_total' => 100.25, 'tax_total' => 0, 'discount_amount' => 0]);
    cart()->setCart($cart);

    $ok = cart()->haveMinimumOrderAmount();
    $this->record('MO-05', null, ['minimum' => '100.50', 'sub_total' => 100.25, 'getOrderAmount' => cart()->getOrderAmount(), 'haveMinimumOrderAmount' => $ok]);

    expect($ok)->toBeFalse();
});
